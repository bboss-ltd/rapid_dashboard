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
        $reasonLabelConfig = config('trello_sync.remake_reason_labels', []);
        $reasonLabels = array_values(array_filter(array_map(function ($label) {
            return mb_strtolower(trim((string) $label));
        }, $reasonLabelConfig)));

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
                $reasonLabel = null;
                if (!empty($reasonLabels)) {
                    $cardLabels = array_map(fn($label) => mb_strtolower(trim((string) $label)), $c['labels'] ?? []);
                    foreach ($reasonLabels as $idx => $label) {
                        if (in_array($label, $cardLabels, true)) {
                            $reasonLabel = $reasonLabelConfig[$idx] ?? null;
                            break;
                        }
                    }
                }

                $remakeCards[] = [
                    'trello_card_id' => $c['trello_card_id'],
                    'card_id' => $card->id,
                    'estimate_points' => $c['estimate_points'] ?? null,
                    'reason_label' => $reasonLabel,
                ];
            }
        }

        $this->trackRemakes->run($sprint, $remakeCards);

        return $snapshot;
    }
}
