<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintSnapshot;
use App\Models\SprintSnapshotCard;

final class TakeSprintSnapshotAction
{
    public function __construct(
        private FetchSprintBoardStateAction $fetchState,
        private TrackRemakeCardsAction $trackRemakes,
    ) {}

    public function run(Sprint $sprint, string $type, string $source = 'scheduled', array $meta = []): SprintSnapshot
    {
        $state = $this->fetchState->run($sprint);
        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));

        $snapshot = SprintSnapshot::create([
            'sprint_id' => $sprint->id,
            'type' => $type,
            'taken_at' => now(),
            'source' => $source,
            'meta' => $meta,
        ]);

        $remakeCards = [];

        foreach ($state['cards'] as $c) {
            $card = Card::updateOrCreate(
                ['trello_card_id' => $c['trello_card_id']],
                [
                    'name' => $c['name'],
                    'last_activity_at' => $c['last_activity_at'],
                ]
            );

            SprintSnapshotCard::create([
                'sprint_snapshot_id' => $snapshot->id,
                'card_id' => $card->id,
                'trello_list_id' => $c['trello_list_id'],
                'estimate_points' => $c['estimate_points'],
                'is_done' => $c['is_done'],
                'meta' => [
                    'estimation_label' => $c['estimation_label'] ?? null,
                ],
            ]);

            if ($sprint->remakes_list_id && $c['trello_list_id'] === $sprint->remakes_list_id) {
                $remakeLabel = trim((string) ($c['remake_label'] ?? ''));
                $normalizedRemake = $this->normalizeLabel($remakeLabel);
                $isRemove = $normalizedRemake !== '' && array_key_exists($normalizedRemake, $removeMap);
                $reasonLabel = $isRemove || $normalizedRemake === '' ? null : $remakeLabel;

                $labelName = $isRemove ? $remakeLabel : null;
                $labelPoints = $isRemove ? ($removeMap[$normalizedRemake] ?? null) : null;

                $remakeCards[] = [
                    'trello_card_id' => $c['trello_card_id'],
                    'card_id' => $card->id,
                    'estimate_points' => $c['estimate_points'] ?? null,
                    'reason_label' => $reasonLabel,
                    'reason_label_color' => null,
                    'label_name' => $labelName,
                    'label_points' => $labelPoints,
                    'trello_reason_label' => $remakeLabel !== '' ? $remakeLabel : null,
                ];
            }
        }

        $this->trackRemakes->run($sprint, $remakeCards);

        return $snapshot;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, string>
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
}
