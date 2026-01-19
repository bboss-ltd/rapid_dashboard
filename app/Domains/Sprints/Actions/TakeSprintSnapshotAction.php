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
    ) {}

    public function run(Sprint $sprint, string $type, string $source = 'scheduled', array $meta = []): SprintSnapshot
    {
        $state = $this->fetchState->run($sprint);

        $snapshot = SprintSnapshot::create([
            'sprint_id' => $sprint->id,
            'type' => $type,
            'taken_at' => now(),
            'source' => $source,
            'meta' => $meta,
        ]);

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
        }

        return $snapshot;
    }
}
