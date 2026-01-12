<?php

namespace App\Domains\TrelloSync\Actions;

use App\Models\BoardSyncCursor;
use App\Models\Sprint;
use App\Models\TrelloAction;
use App\Services\Trello\TrelloClient;
use Illuminate\Support\Carbon;

final class PollBoardActionsAction
{
    public function __construct(private TrelloClient $trello) {}

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
            return;
        }

        // Trello returns newest-first; process oldest-first for stable projection later
        $actions = array_reverse($actions);

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
        }

        $cursor->last_polled_at = now();
        $cursor->save();

        $sprint->last_polled_at = now();
        $sprint->save();
    }
}
