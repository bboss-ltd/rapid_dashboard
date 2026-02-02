<?php

namespace App\Domains\TrelloSync\Actions;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Models\TrelloAction;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Support\Carbon;

final class ApplyRemakeLabelActionsAction
{
    public function __construct(private TrelloSprintBoardReader $reader) {}

    public function run(Sprint $sprint): void
    {
        $labelsConfig = config('trello_sync.remake_label_actions', []);
        $pointsMap = $this->normalizeLabelPoints($labelsConfig['remove'] ?? []);

        $customFields = $this->reader->fetchCustomFields($sprint->trello_board_id);
        $lookup = $this->reader->buildDropdownLookup($customFields);
        $remakeLabelFieldName = (string) config('trello_sync.sprint_board.remake_label_field_name', 'Remake Label');
        $remakeLabelFieldId = $remakeLabelFieldName !== ''
            ? $this->reader->findCustomFieldIdByName($customFields, $remakeLabelFieldName)
            : null;

        $actions = TrelloAction::query()
            ->where('trello_board_id', $sprint->trello_board_id)
            ->whereNull('processed_at')
            ->whereIn('type', ['deleteCard', 'updateCustomFieldItem'])
            ->orderBy('occurred_at')
            ->get();

        foreach ($actions as $action) {
            $payload = is_array($action->payload) ? $action->payload : [];
            $cardId = $payload['data']['card']['id'] ?? null;
            $occurredAt = $action->occurred_at ?? now();

            if ($action->type === 'deleteCard' && $cardId) {
                $this->markRemoved($sprint, $cardId, $occurredAt);
            }

            if ($action->type === 'updateCustomFieldItem' && $cardId && $remakeLabelFieldId) {
                $fieldId = $payload['data']['customField']['id'] ?? null;
                if ($fieldId && $fieldId === $remakeLabelFieldId) {
                    $idValue = $payload['data']['customFieldItem']['idValue'] ?? null;
                    $textValue = $payload['data']['customFieldItem']['value']['text'] ?? null;
                    $label = null;
                    if (is_string($idValue) && $idValue !== '') {
                        $label = $lookup[$remakeLabelFieldId][$idValue] ?? null;
                    } elseif (is_string($textValue) && $textValue !== '') {
                        $label = $textValue;
                    }

                    $label = is_string($label) ? trim($label) : null;
                    if ($label === null || $label === '') {
                        $this->clearRemakeLabel($sprint, $cardId, $occurredAt);
                    } elseif (array_key_exists($this->normalizeLabel($label), $pointsMap)) {
                        $this->applyRemoveFromDropdown(
                            $sprint,
                            $cardId,
                            $occurredAt,
                            $label,
                            $pointsMap[$this->normalizeLabel($label)]
                        );
                    } else {
                        $this->applyReasonFromDropdown($sprint, $cardId, $occurredAt, $label);
                    }
                }
            }

            $action->processed_at = now();
            $action->save();
        }
    }

    /**
     * @param array<string, int|float|string> $labels
     * @return array<string, int>
     */
    private function normalizeLabelPoints(array $labels): array
    {
        $out = [];
        foreach ($labels as $label => $points) {
            $name = $this->normalizeLabel((string) $label);
            if ($name === '') {
                continue;
            }
            $out[$name] = (int) $points;
        }
        return $out;
    }

    private function applyLabelPoints(Sprint $sprint, string $trelloCardId, Carbon $occurredAt, string $labelName, int $points): SprintRemake
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

        return $record;
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

    private function applyReasonLabel(Sprint $sprint, string $trelloCardId, Carbon $occurredAt, string $labelName): SprintRemake
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
            'reason_label_color' => null,
            'reason_set_at' => $occurredAt,
            'last_seen_at' => $occurredAt,
        ]);

        return $record;
    }

    private function clearRemakeLabel(Sprint $sprint, string $trelloCardId, Carbon $occurredAt): void
    {
        $record = SprintRemake::query()
            ->where('sprint_id', $sprint->id)
            ->where('trello_card_id', $trelloCardId)
            ->first();

        if (!$record) {
            return;
        }

        $record->update([
            'trello_reason_label' => null,
            'trello_reason_set_at' => null,
            'reason_label' => null,
            'reason_label_color' => null,
            'reason_set_at' => null,
            'label_name' => null,
            'label_points' => null,
            'label_set_at' => null,
            'last_seen_at' => $occurredAt,
        ]);
    }

    private function applyRemoveFromDropdown(
        Sprint $sprint,
        string $trelloCardId,
        Carbon $occurredAt,
        string $labelName,
        int $points
    ): void {
        $record = $this->applyLabelPoints($sprint, $trelloCardId, $occurredAt, $labelName, $points);
        $record->update([
            'trello_reason_label' => $labelName,
            'trello_reason_set_at' => $occurredAt,
            'reason_label' => null,
            'reason_label_color' => null,
            'reason_set_at' => null,
        ]);
    }

    private function applyReasonFromDropdown(
        Sprint $sprint,
        string $trelloCardId,
        Carbon $occurredAt,
        string $labelName
    ): void {
        $record = $this->applyReasonLabel($sprint, $trelloCardId, $occurredAt, $labelName);
        $record->update([
            'trello_reason_label' => $labelName,
            'trello_reason_set_at' => $occurredAt,
            'label_name' => null,
            'label_points' => null,
            'label_set_at' => null,
        ]);
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
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
