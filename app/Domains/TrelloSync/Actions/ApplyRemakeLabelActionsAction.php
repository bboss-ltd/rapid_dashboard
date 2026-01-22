<?php

namespace App\Domains\TrelloSync\Actions;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Models\TrelloAction;
use Illuminate\Support\Carbon;

final class ApplyRemakeLabelActionsAction
{
    public function run(Sprint $sprint): void
    {
        $labelsConfig = config('trello_sync.remake_label_actions', []);
        $pointsMap = $this->normalizeLabelPoints($labelsConfig['remove'] ?? []);
        $restoreLabels = $this->normalizeLabels($labelsConfig['restore'] ?? []);
        $reasonLabels = $this->normalizeLabels(config('trello_sync.remake_reason_labels', []));

        $actions = TrelloAction::query()
            ->where('trello_board_id', $sprint->trello_board_id)
            ->whereNull('processed_at')
            ->whereIn('type', ['addLabelToCard', 'removeLabelFromCard', 'deleteCard'])
            ->orderBy('occurred_at')
            ->get();

        foreach ($actions as $action) {
            $payload = is_array($action->payload) ? $action->payload : [];
            $rawLabel = trim((string) ($payload['data']['label']['name'] ?? ''));
            $rawColor = trim((string) ($payload['data']['label']['color'] ?? ''));
            $labelName = strtolower($rawLabel);
            $cardId = $payload['data']['card']['id'] ?? null;
            $occurredAt = $action->occurred_at ?? now();

            if ($cardId && $labelName !== '') {
                if (array_key_exists($labelName, $pointsMap)) {
                    if ($action->type === 'addLabelToCard') {
                        $this->applyLabelPoints($sprint, $cardId, $occurredAt, $rawLabel, $pointsMap[$labelName]);
                    } elseif ($action->type === 'removeLabelFromCard') {
                        $this->clearLabelPoints($sprint, $cardId, $occurredAt);
                    }
                } elseif (in_array($labelName, $restoreLabels, true) && $action->type === 'addLabelToCard') {
                    $this->clearLabelPoints($sprint, $cardId, $occurredAt);
                }

                if (in_array($labelName, $reasonLabels, true)) {
                    if ($action->type === 'addLabelToCard') {
                        $this->applyReasonLabel($sprint, $cardId, $occurredAt, $rawLabel, $rawColor);
                    } elseif ($action->type === 'removeLabelFromCard') {
                        $this->clearReasonLabel($sprint, $cardId, $occurredAt, $rawLabel);
                    }
                }
            }

            if ($action->type === 'deleteCard' && $cardId) {
                $this->markRemoved($sprint, $cardId, $occurredAt);
            }

            $action->processed_at = now();
            $action->save();
        }
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function normalizeLabels(array $labels): array
    {
        return array_values(array_filter(array_map(function ($label) {
            $label = strtolower(trim((string) $label));
            return $label !== '' ? $label : null;
        }, $labels)));
    }

    /**
     * @param array<string, int|float|string> $labels
     * @return array<string, int>
     */
    private function normalizeLabelPoints(array $labels): array
    {
        $out = [];
        foreach ($labels as $label => $points) {
            $name = strtolower(trim((string) $label));
            if ($name === '') {
                continue;
            }
            $out[$name] = (int) $points;
        }
        return $out;
    }

    private function applyLabelPoints(Sprint $sprint, string $trelloCardId, Carbon $occurredAt, string $labelName, int $points): void
    {
        $card = Card::query()->where('trello_card_id', $trelloCardId)->first();

        $record = SprintRemake::firstOrCreate(
            [
                'sprint_id' => $sprint->id,
                'trello_card_id' => $trelloCardId,
            ],
            [
                'card_id' => $card?->id,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
            ]
        );

        $record->update([
            'card_id' => $record->card_id ?: $card?->id,
            'label_name' => $labelName,
            'label_points' => $points,
            'label_set_at' => $occurredAt,
            'last_seen_at' => $occurredAt,
        ]);
    }

    private function clearLabelPoints(Sprint $sprint, string $trelloCardId, Carbon $occurredAt): void
    {
        $record = SprintRemake::query()
            ->where('sprint_id', $sprint->id)
            ->where('trello_card_id', $trelloCardId)
            ->first();

        if (!$record) {
            return;
        }

        $record->update([
            'label_name' => null,
            'label_points' => null,
            'label_set_at' => null,
            'last_seen_at' => $occurredAt,
        ]);
    }

    private function applyReasonLabel(Sprint $sprint, string $trelloCardId, Carbon $occurredAt, string $labelName, string $labelColor = ''): void
    {
        $card = Card::query()->where('trello_card_id', $trelloCardId)->first();

        $record = SprintRemake::firstOrCreate(
            [
                'sprint_id' => $sprint->id,
                'trello_card_id' => $trelloCardId,
            ],
            [
                'card_id' => $card?->id,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
            ]
        );

        $record->update([
            'card_id' => $record->card_id ?: $card?->id,
            'reason_label' => $labelName,
            'reason_label_color' => $labelColor !== '' ? $labelColor : $record->reason_label_color,
            'reason_set_at' => $occurredAt,
            'last_seen_at' => $occurredAt,
        ]);
    }

    private function clearReasonLabel(Sprint $sprint, string $trelloCardId, Carbon $occurredAt, string $labelName): void
    {
        $record = SprintRemake::query()
            ->where('sprint_id', $sprint->id)
            ->where('trello_card_id', $trelloCardId)
            ->first();

        if (!$record) {
            return;
        }

        if (mb_strtolower((string) $record->reason_label) !== mb_strtolower($labelName)) {
            return;
        }

        $record->update([
            'reason_label' => null,
            'reason_label_color' => null,
            'reason_set_at' => null,
            'last_seen_at' => $occurredAt,
        ]);
    }

    private function markRemoved(Sprint $sprint, string $trelloCardId, Carbon $occurredAt): void
    {
        $card = Card::query()->where('trello_card_id', $trelloCardId)->first();

        $record = SprintRemake::firstOrCreate(
            [
                'sprint_id' => $sprint->id,
                'trello_card_id' => $trelloCardId,
            ],
            [
                'card_id' => $card?->id,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
                'removed_at' => $occurredAt,
            ]
        );

        $record->update([
            'card_id' => $record->card_id ?: $card?->id,
            'last_seen_at' => $occurredAt,
            'removed_at' => $occurredAt,
        ]);
    }
}
