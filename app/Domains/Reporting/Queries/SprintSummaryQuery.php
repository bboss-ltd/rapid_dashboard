<?php

namespace App\Domains\Reporting\Queries;

use App\Models\Sprint;
use Illuminate\Support\Facades\DB;

final class SprintSummaryQuery
{
    /**
     * Returns a summary for a sprint, anchored to its latest END snapshot.
     */
    public function run(Sprint $sprint): array
    {
        $end = $sprint->snapshots()
            ->where('type', 'end')
            ->latest('taken_at')
            ->first();

        if (!$end) {
            return [
                'has_end_snapshot' => false,
                'message' => 'No end snapshot found for this sprint yet.',
            ];
        }

        // Optional: latest START snapshot (for comparison)
        $start = $sprint->snapshots()
            ->where('type', 'start')
            ->latest('taken_at')
            ->first();

        $endAgg = $this->aggregateSnapshot($end->id);
        $rolloverAgg = $this->aggregateRollover($end->id);
        $remakeAgg = $this->aggregateRemakes($sprint->id);

        $startAgg = $start ? $this->aggregateSnapshot($start->id) : null;

        $changes = $start ? $this->diffStartToEnd($start->id, $end->id) : null;

        return [
            'has_end_snapshot' => true,
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'starts_at' => optional($sprint->starts_at)->toIso8601String(),
                'ends_at' => optional($sprint->ends_at)->toIso8601String(),
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
                'trello_board_id' => $sprint->trello_board_id,
            ],
            'snapshots' => [
                'start' => $start ? [
                    'id' => $start->id,
                    'taken_at' => $start->taken_at->toIso8601String(),
                    'source' => $start->source,
                ] : null,
                'end' => [
                    'id' => $end->id,
                    'taken_at' => $end->taken_at->toIso8601String(),
                    'source' => $end->source,
                    'meta' => $end->meta,
                ],
            ],
            'end_totals' => [
                'scope_points' => $endAgg['scope_points'],
                'completed_points' => $endAgg['done_points'],
                'remaining_points' => $endAgg['remaining_points'],
                'cards_total' => $endAgg['cards_total'],
                'cards_done' => $endAgg['cards_done'],
                'cards_not_done' => $endAgg['cards_not_done'],
            ],
            'rollover' => [
                'cards_count' => $rolloverAgg['cards_count'],
                'points' => $rolloverAgg['points'],
            ],
            'remakes' => [
                'cards_count' => $remakeAgg['cards_count'],
                'points_raw' => $remakeAgg['points_raw'],
                'points_adjusted' => $remakeAgg['points_adjusted'],
            ],
            'start_totals' => $startAgg ? [
                'scope_points' => $startAgg['scope_points'],
                'cards_total' => $startAgg['cards_total'],
            ] : null,
            'changes_start_to_end' => $changes,
        ];
    }

    private function aggregateSnapshot(int $snapshotId): array
    {
        $row = DB::table('sprint_snapshot_cards')
            ->where('sprint_snapshot_id', $snapshotId)
            ->selectRaw('
                COUNT(*) as cards_total,
                SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) as cards_done,
                SUM(CASE WHEN is_done = 0 THEN 1 ELSE 0 END) as cards_not_done,
                COALESCE(SUM(COALESCE(estimate_points,0)),0) as scope_points,
                COALESCE(SUM(CASE WHEN is_done = 1 THEN COALESCE(estimate_points,0) ELSE 0 END),0) as done_points
            ')
            ->first();

        $scope = (int) ($row->scope_points ?? 0);
        $done = (int) ($row->done_points ?? 0);

        return [
            'cards_total' => (int) ($row->cards_total ?? 0),
            'cards_done' => (int) ($row->cards_done ?? 0),
            'cards_not_done' => (int) ($row->cards_not_done ?? 0),
            'scope_points' => $scope,
            'done_points' => $done,
            'remaining_points' => max(0, $scope - $done),
        ];
    }

    private function aggregateRollover(int $endSnapshotId): array
    {
        $row = DB::table('sprint_snapshot_cards')
            ->where('sprint_snapshot_id', $endSnapshotId)
            ->where('is_done', 0)
            ->selectRaw('
                COUNT(*) as cards_count,
                COALESCE(SUM(COALESCE(estimate_points,0)),0) as points
            ')
            ->first();

        return [
            'cards_count' => (int) ($row->cards_count ?? 0),
            'points' => (int) ($row->points ?? 0),
        ];
    }

    private function aggregateRemakes(int $sprintId): array
    {
        $row = DB::table('sprint_remakes')
            ->where('sprint_id', $sprintId)
            ->whereNull('removed_at')
            ->selectRaw('
                COUNT(*) as cards_count,
                COALESCE(SUM(COALESCE(estimate_points,0)),0) as points_raw,
                COALESCE(SUM(COALESCE(label_points, estimate_points, 0)),0) as points_adjusted
            ')
            ->first();

        return [
            'cards_count' => (int) ($row->cards_count ?? 0),
            'points_raw' => (int) ($row->points_raw ?? 0),
            'points_adjusted' => (int) ($row->points_adjusted ?? 0),
        ];
    }

    /**
     * Returns counts of changes between start and end snapshots:
     * - scope added/removed (cards)
     * - estimate changes (points changed for cards present in both)
     */
    private function diffStartToEnd(int $startSnapshotId, int $endSnapshotId): array
    {
        // Cards in start/end keyed by card_id
        $start = DB::table('sprint_snapshot_cards')
            ->where('sprint_snapshot_id', $startSnapshotId)
            ->select('card_id', 'estimate_points')
            ->get()
            ->keyBy('card_id');

        $end = DB::table('sprint_snapshot_cards')
            ->where('sprint_snapshot_id', $endSnapshotId)
            ->select('card_id', 'estimate_points', 'is_done')
            ->get()
            ->keyBy('card_id');

        $startIds = $start->keys();
        $endIds = $end->keys();

        $added = $endIds->diff($startIds)->count();
        $removed = $startIds->diff($endIds)->count();

        $estimateChanged = 0;
        $changedPointsTotalAbs = 0;

        foreach ($startIds->intersect($endIds) as $cardId) {
            $sp = (int) (($start[$cardId]->estimate_points ?? 0));
            $ep = (int) (($end[$cardId]->estimate_points ?? 0));
            if ($sp !== $ep) {
                $estimateChanged++;
                $changedPointsTotalAbs += abs($ep - $sp);
            }
        }

        return [
            'cards_added_to_scope' => $added,
            'cards_removed_from_scope' => $removed,
            'cards_with_estimate_changed' => $estimateChanged,
            'total_absolute_points_change' => $changedPointsTotalAbs,
        ];
    }
}
