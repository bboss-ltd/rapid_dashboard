<?php

namespace App\Domains\Reporting\Queries;

use App\Models\Sprint;
use App\Models\SprintSnapshot;

final class RolloverCardsQuery
{
    /**
     * Cards to roll over = cards not done in the END snapshot.
     * Returns minimal info for display/reporting.
     *
     * @return array<int, array{trello_card_id:string, name:string, estimate_points:int, trello_list_id:string}>
     */
    public function run(Sprint $sprint): array
    {
        /** @var SprintSnapshot|null $end */
        $end = $sprint->snapshots()
            ->where('type', 'end')
            ->latest('taken_at')
            ->first();

        if (!$end) {
            return [];
        }

        $rows = $end->cards()
            ->where('is_done', false)
            ->with('card:id,trello_card_id,name')
            ->get(['card_id', 'estimate_points', 'trello_list_id', 'is_done']);

        return $rows->map(function ($r) {
            return [
                'trello_card_id' => $r->card->trello_card_id,
                'name' => $r->card->name,
                'estimate_points' => (int) ($r->estimate_points ?? 0),
                'trello_list_id' => $r->trello_list_id,
            ];
        })->values()->all();
    }
}
