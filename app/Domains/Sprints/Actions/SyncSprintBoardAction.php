<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Estimation\EstimateConverter;
use App\Models\Card;
use App\Models\Sprint;
use App\Services\Trello\TrelloSprintBoardReader;

class SyncSprintBoardAction
{
    public function __construct(
        private TrelloSprintBoardReader $reader,
        private EstimateConverter $converter,
    ) {}

    public function run(Sprint $sprint): void
    {
        $customFields = $this->reader->fetchCustomFields($sprint->trello_board_id);
        $lookup = $this->reader->buildDropdownLookup($customFields);

        $estimationFieldId = $this->reader->findCustomFieldIdByName($customFields, 'Estimation');
        if (!$estimationFieldId) {
            // Fail loudly for POC; later convert to domain exception
            throw new \RuntimeException('Custom field "Estimation" not found on board.');
        }

        $cards = $this->reader->fetchCards($sprint->trello_board_id);

        foreach ($cards as $c) {
            $estimationText = $this->reader->resolveDropdownText($c, $estimationFieldId, $lookup);
            $points = $this->converter->toPoints($estimationText);

            Card::updateOrCreate(
                ['sprint_id' => $sprint->id, 'trello_card_id' => $c['id']],
                [
                    'name' => $c['name'] ?? '(no name)',
                    'trello_list_id' => $c['idList'] ?? '',
                    'last_activity_at' => isset($c['dateLastActivity']) ? Carbon::parse($c['dateLastActivity']) : null,
                    'estimation_text' => $estimationText,
                    'estimate_points' => $points,
                ]
            );
        }
    }
}
