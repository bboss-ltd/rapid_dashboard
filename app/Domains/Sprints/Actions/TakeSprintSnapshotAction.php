<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Estimation\EstimatePointsResolver;
use App\Models\Card;
use App\Models\Sprint;
use App\Models\SprintSnapshot;
use App\Models\SprintSnapshotCard;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Support\Carbon;

final class TakeSprintSnapshotAction
{
    public function __construct(
        private TrelloSprintBoardReader $reader,
        private EstimatePointsResolver $pointsResolver,
    ) {}

    public function run(Sprint $sprint, string $type, string $source = 'scheduled', array $meta = []): SprintSnapshot
    {
        $boardId = $sprint->trello_board_id;

        $customFields = $this->reader->fetchCustomFields($boardId);
        $lookup = $this->reader->buildDropdownLookup($customFields);
        $estimationFieldId = $this->reader->findCustomFieldIdByName($customFields, 'Estimation');

        $cards = $this->reader->fetchCards($boardId);
        $doneLists = collect($sprint->done_list_ids ?? []);

        $snapshot = SprintSnapshot::create([
            'sprint_id' => $sprint->id,
            'type' => $type,
            'taken_at' => now(),
            'source' => $source,
            'meta' => $meta,
        ]);

        foreach ($cards as $c) {
            $trelloCardId = $c['id'] ?? null;
            if (!$trelloCardId) continue;

            $card = Card::updateOrCreate(
                ['trello_card_id' => $trelloCardId],
                [
                    'name' => $c['name'] ?? '(no name)',
                    'last_activity_at' => isset($c['dateLastActivity']) ? Carbon::parse($c['dateLastActivity']) : null,
                ]
            );

            $listId = $c['idList'] ?? '';
            $isDone = $doneLists->contains($listId);

            $label = null;
            if ($estimationFieldId) {
                $label = $this->reader->resolveDropdownText($c, $estimationFieldId, $lookup);
            }

            $points = $this->pointsResolver->pointsForLabel($label);

            SprintSnapshotCard::create([
                'sprint_snapshot_id' => $snapshot->id,
                'card_id' => $card->id,
                'trello_list_id' => $listId,
                'estimate_points' => $points,
                'is_done' => $isDone,
                'meta' => [
                    'estimation_label' => $label, // optional; remove if you truly never want it stored
                ],
            ]);
        }

        return $snapshot;
    }
}
