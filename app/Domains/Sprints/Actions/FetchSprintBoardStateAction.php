<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Estimation\EstimatePointsResolver;
use App\Models\Sprint;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Support\Carbon;

final class FetchSprintBoardStateAction
{
    public function __construct(
        private TrelloSprintBoardReader $reader,
        private EstimatePointsResolver $pointsResolver,
    ) {}

    /**
     * Fetch the current board state for this sprint in a normalized structure.
     *
     * @return array{
     *   taken_at: \DateTimeInterface,
     *   cards: array<int, array{
     *     trello_card_id: string,
     *     name: string,
     *     trello_list_id: string,
     *     last_activity_at: \DateTimeInterface|null,
     *     estimate_points: int|null,
     *     estimation_label: string|null,
     *     remake_label: string|null,
     *     production_line: string|null,
     *     labels: array<int, string>,
     *     is_done: bool
     *   }>
     * }
     */
    public function run(Sprint $sprint): array
    {
        $boardId = $sprint->trello_board_id;

        // Done lists configured per sprint
        $doneLists = collect($sprint->done_list_ids ?? []);

        // Custom fields for the board (used to resolve dropdown option id -> label)
        $customFields = $this->reader->fetchCustomFields($boardId);
        $lookup = $this->reader->buildDropdownLookup($customFields);

        // "Estimation" dropdown custom field id
        $estimationFieldId = $this->reader->findCustomFieldIdByName($customFields, 'Estimation');
        $remakeLabelFieldName = (string) config('trello_sync.sprint_board.remake_label_field_name', 'Remake Label');
        $remakeLabelFieldId = $remakeLabelFieldName !== ''
            ? $this->reader->findCustomFieldIdByName($customFields, $remakeLabelFieldName)
            : null;
        $productionLineFieldName = (string) config('trello_sync.sprint_board.production_line_field_name', 'Production Line');
        $productionLineFieldId = $productionLineFieldName !== ''
            ? $this->reader->findCustomFieldIdByName($customFields, $productionLineFieldName)
            : null;

        // Cards on board (includes customFieldItems)
        $cards = $this->reader->fetchCards($boardId);

        $out = [];

        foreach ($cards as $c) {
            $trelloCardId = $c['id'] ?? null;
            if (!$trelloCardId) continue;

            $listId = $c['idList'] ?? '';
            $isDone = $doneLists->contains($listId);

            $label = null;
            if ($estimationFieldId) {
                $label = $this->reader->resolveDropdownText($c, $estimationFieldId, $lookup);
            }

            $remakeLabel = null;
            if ($remakeLabelFieldId) {
                $remakeLabel = $this->reader->resolveDropdownText($c, $remakeLabelFieldId, $lookup);
            }

            $productionLine = null;
            if ($productionLineFieldId) {
                $productionLine = $this->reader->resolveDropdownText($c, $productionLineFieldId, $lookup);
            }

            $points = $this->pointsResolver->pointsForLabel($label);

            $labels = array_values(array_filter(array_map(function ($label) {
                $name = $label['name'] ?? null;
                if ($name === null || $name === '') {
                    return null;
                }
                return [
                    'name' => $name,
                    'color' => $label['color'] ?? null,
                ];
            }, $c['labels'] ?? [])));

            $out[] = [
                'trello_card_id' => $trelloCardId,
                'name' => $c['name'] ?? '(no name)',
                'trello_list_id' => $listId,
                'last_activity_at' => isset($c['dateLastActivity']) ? Carbon::parse($c['dateLastActivity']) : null,
                'estimate_points' => $points,
                'estimation_label' => $label,
                'remake_label' => $remakeLabel,
                'production_line' => $productionLine,
                'labels' => $labels,
                'is_done' => $isDone,
            ];
        }

        return [
            'taken_at' => now(),
            'cards' => $out,
        ];
    }
}
