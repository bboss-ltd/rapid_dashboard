<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Sprint;

class SprintOpsController extends Controller
{
    public function show(Sprint $sprint)
    {
        $latestAdHoc = $sprint->snapshots()
            ->where('type', 'ad_hoc')
            ->latest('taken_at')
            ->first();

        $latestEnd = $sprint->snapshots()
            ->where('type', 'end')
            ->latest('taken_at')
            ->first();

        return response()->json([
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'trello_board_id' => $sprint->trello_board_id,
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
            ],
            'snapshots' => [
                'latest_ad_hoc' => $latestAdHoc ? [
                    'id' => $latestAdHoc->id,
                    'taken_at' => $latestAdHoc->taken_at->toIso8601String(),
                    'source' => $latestAdHoc->source,
                    'meta' => $latestAdHoc->meta,
                ] : null,
                'latest_end' => $latestEnd ? [
                    'id' => $latestEnd->id,
                    'taken_at' => $latestEnd->taken_at->toIso8601String(),
                    'source' => $latestEnd->source,
                    'meta' => $latestEnd->meta,
                ] : null,
            ],
        ]);
    }
}
