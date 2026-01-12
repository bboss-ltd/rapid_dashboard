<?php

namespace App\Domains\Reporting\Queries;

use App\Models\Sprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BurndownSeriesQuery
{
    /**
     * @return Collection<int, array{taken_at:string, scope_points:int, done_points:int, remaining_points:int}>
     */
    public function run(Sprint $sprint, array $types = ['ad_hoc', 'end']): Collection
    {
        // Aggregate snapshot cards in one pass
        $rows = DB::table('sprint_snapshots as ss')
            ->join('sprint_snapshot_cards as ssc', 'ssc.sprint_snapshot_id', '=', 'ss.id')
            ->where('ss.sprint_id', $sprint->id)
            ->whereIn('ss.type', $types)
            ->groupBy('ss.id', 'ss.taken_at')
            ->orderBy('ss.taken_at')
            ->select([
                'ss.taken_at',
                DB::raw('COALESCE(SUM(COALESCE(ssc.estimate_points,0)),0) as scope_points'),
                DB::raw('COALESCE(SUM(CASE WHEN ssc.is_done = 1 THEN COALESCE(ssc.estimate_points,0) ELSE 0 END),0) as done_points'),
            ])
            ->get();

        return collect($rows)->map(function ($r) {
            $scope = (int) $r->scope_points;
            $done = (int) $r->done_points;
            $remaining = max(0, $scope - $done);

            return [
                'taken_at' => \Illuminate\Support\Carbon::parse($r->taken_at)->toIso8601String(),
                'scope_points' => $scope,
                'done_points' => $done,
                'remaining_points' => $remaining,
            ];
        });
    }
}
