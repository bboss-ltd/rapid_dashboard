<?php

namespace App\Domains\TrelloSync\Actions;

use App\Models\BoardSyncCursor;
use App\Models\Sprint;
use App\Models\TrelloAction;
use App\Services\Trello\TrelloClient;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class PollBoardActionsAction
{
    public function __construct(
        private TrelloClient $trello,
        private ApplyRemakeLabelActionsAction $applyRemakeLabels,
        private TakeSprintSnapshotAction $takeSnapshot,
    ) {}

    public function run(Sprint $sprint): void
    {
        $boardId = $sprint->trello_board_id;

        $cursor = BoardSyncCursor::firstOrCreate(
            ['trello_board_id' => $boardId],
            ['last_action_occurred_at' => null, 'last_action_id' => null]
        );

        $filter = implode(',', config('trello_sync.action_types', []));
        $limit = (int) config('trello_sync.poll_limit', 200);

        $query = [
            'filter' => $filter,
            'limit' => $limit,
        ];

        // Use `since` if we have a cursor time; Trello supports incremental queries on nested actions.
        if ($cursor->last_action_occurred_at) {
            $query['since'] = Carbon::parse($cursor->last_action_occurred_at)->toIso8601String();
        }

        $actions = $this->trello->get("/boards/{$boardId}/actions", $query);

        if (!is_array($actions) || count($actions) === 0) {
            $cursor->last_polled_at = now();
            $cursor->save();
            $sprint->last_polled_at = now();
            $sprint->save();
            $this->applyRemakeLabels->run($sprint);
            $this->clearWallboardCache($sprint, ['remakes', 'reasons']);
            return;
        }

        // Trello returns newest-first; process oldest-first for stable projection later
        $actions = array_reverse($actions);

        $shouldSnapshot = false;

        foreach ($actions as $a) {
            $actionId = $a['id'] ?? null;
            if (!$actionId) continue;

            TrelloAction::updateOrCreate(
                ['trello_action_id' => $actionId],
                [
                    'trello_board_id' => $boardId,
                    'trello_card_id' => $a['data']['card']['id'] ?? null,
                    'type' => $a['type'] ?? 'unknown',
                    'occurred_at' => isset($a['date']) ? Carbon::parse($a['date']) : now(),
                    'payload' => $a,
                ]
            );

            // advance cursor as we go
            $cursor->last_action_occurred_at = Carbon::parse($a['date'] ?? now());
            $cursor->last_action_id = $actionId;

            if (!$shouldSnapshot && $this->actionTriggersSnapshot($sprint, $a)) {
                $shouldSnapshot = true;
            }
        }

        $cursor->last_polled_at = now();
        $cursor->save();

        $sprint->last_polled_at = now();
        $sprint->save();

        $this->applyRemakeLabels->run($sprint);
        $this->clearWallboardCache($sprint, ['remakes', 'reasons']);

        if ($shouldSnapshot) {
            $this->takeSnapshot->run($sprint, 'ad_hoc', 'trello_poll');
            $this->clearWallboardCache($sprint, ['burndown', 'remakes', 'reasons']);
        }
    }

    private function actionTriggersSnapshot(Sprint $sprint, array $action): bool
    {
        $type = (string) ($action['type'] ?? '');
        if ($type === '') {
            return false;
        }

        $simpleTriggers = [
            'createCard',
            'deleteCard',
            'moveCardToBoard',
            'moveCardFromBoard',
            'updateCard:closed',
        ];
        if (in_array($type, $simpleTriggers, true)) {
            return true;
        }

        if (str_starts_with($type, 'updateCustomFieldItem')) {
            $fieldName = trim((string) ($action['data']['customField']['name'] ?? ''));
            if ($fieldName === '') {
                return false;
            }
            $triggerFields = array_values(array_filter(array_map(
                'trim',
                (array) config('trello_sync.burndown_trigger_custom_fields', [])
            )));
            $normalized = strtolower($fieldName);
            foreach ($triggerFields as $field) {
                if ($normalized === strtolower($field)) {
                    return true;
                }
            }
        }

        if ($type === 'updateCard:idList') {
            $before = $action['data']['listBefore']['id'] ?? null;
            $after = $action['data']['listAfter']['id'] ?? null;
            $doneIds = array_values(array_filter(array_map('trim', (array) ($sprint->done_list_ids ?? []))));
            if ($doneIds === []) {
                return false;
            }
            return in_array($before, $doneIds, true) || in_array($after, $doneIds, true);
        }

        if ($type === 'updateCard') {
            $oldClosed = $action['data']['old']['closed'] ?? null;
            $newClosed = $action['data']['card']['closed'] ?? null;
            if ($oldClosed !== null || $newClosed !== null) {
                return true;
            }
        }

        return false;
    }

    private function clearWallboardCache(Sprint $sprint, array $keys): void
    {
        $prefix = "wallboard:{$sprint->id}:";
        foreach ($keys as $key) {
            Cache::forget($prefix . $key);
        }

        Cache::forget($prefix . 'remakes');
        Cache::forget($prefix . 'remakes:now');
        Cache::forget($prefix . 'reasons');
        Cache::forget($prefix . 'reasons:' . now()->toDateString());
        Cache::forget($prefix . 'reasons-by-line');
        Cache::forget($prefix . 'reasons-by-line:' . now()->toDateString());
    }
}
