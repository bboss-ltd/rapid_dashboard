<?php

namespace App\Domains\Reporting\Queries;

use App\Models\Sprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VelocityBySprintQuery
{
    /**
     * Velocity = sum(estimate_points) for DONE cards in END snapshot.
     *
     * @return Collection<int, array{
     *   sprint_id:int,
     *   sprint_name:string,
     *   starts_at:?string,
     *   ends_at:?string,
     *   closed_at:?string,
     *   completed_points:int,
     *   scope_points:int,
     *   remaining_points:int
     * }>
     */
    public function run(): Collection
    {
        // For each sprint, pick latest END snapshot id
        $latestEndSnapshots = DB::table('sprint_snapshots')
            ->select('sprint_id', DB::raw('MAX(taken_at) as max_taken_at'))
            ->where('type', 'end')
            ->groupBy('sprint_id');

        $endSnapshotIds = DB::table('sprint_snapshots as ss')
            ->joinSub($latestEndSnapshots, 'le', function ($join) {
                $join->on('ss.sprint_id', '=', 'le.sprint_id')
                    ->on('ss.taken_at', '=', 'le.max_taken_at');
            })
            ->where('ss.type', 'end')
            ->select('ss.id', 'ss.sprint_id')
            ->get()
            ->keyBy('sprint_id');

        $remakeAgg = DB::table('sprint_remakes')
            ->whereNull('removed_at')
            ->groupBy('sprint_id')
            ->select('sprint_id')
            ->selectRaw('
                COUNT(*) as remake_cards_count,
                COALESCE(SUM(COALESCE(estimate_points,0)),0) as remake_points_raw,
                COALESCE(SUM(COALESCE(label_points, estimate_points, 0)),0) as remake_points_adjusted
            ')
            ->get()
            ->keyBy('sprint_id');

        $sprints = Sprint::query()
            ->orderBy('starts_at')
            ->get();

        return $sprints->map(function (Sprint $sprint) use ($endSnapshotIds, $remakeAgg) {
            $end = $endSnapshotIds->get($sprint->id);
            $remakeRow = $remakeAgg->get($sprint->id);
            if (!$end) {
                return [
                    'sprint_id' => $sprint->id,
                    'sprint_name' => $sprint->name,
                    'starts_at' => optional($sprint->starts_at)->toIso8601String(),
                    'ends_at' => optional($sprint->ends_at)->toIso8601String(),
                    'closed_at' => optional($sprint->closed_at)->toIso8601String(),
                    'completed_points' => 0,
                    'scope_points' => 0,
                    'remaining_points' => 0,
                    'remake_cards_count' => (int) ($remakeRow->remake_cards_count ?? 0),
                    'remake_points_raw' => (int) ($remakeRow->remake_points_raw ?? 0),
                    'remake_points_adjusted' => (int) ($remakeRow->remake_points_adjusted ?? 0),
                ];
            }

            $agg = DB::table('sprint_snapshot_cards')
                ->where('sprint_snapshot_id', $end->id)
                ->selectRaw('
                    COALESCE(SUM(COALESCE(estimate_points,0)),0) as scope_points,
                    COALESCE(SUM(CASE WHEN is_done = 1 THEN COALESCE(estimate_points,0) ELSE 0 END),0) as done_points
                ')
                ->first();

            $scope = (int) ($agg->scope_points ?? 0);
            $done = (int) ($agg->done_points ?? 0);

            return [
                'sprint_id' => $sprint->id,
                'sprint_name' => $sprint->name,
                'starts_at' => optional($sprint->starts_at)->toIso8601String(),
                'ends_at' => optional($sprint->ends_at)->toIso8601String(),
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
                'completed_points' => $done,
                'scope_points' => $scope,
                'remaining_points' => max(0, $scope - $done),
                'remake_cards_count' => (int) ($remakeRow->remake_cards_count ?? 0),
                'remake_points_raw' => (int) ($remakeRow->remake_points_raw ?? 0),
                'remake_points_adjusted' => (int) ($remakeRow->remake_points_adjusted ?? 0),
            ];
        });
    }
}
