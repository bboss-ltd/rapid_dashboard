<?php

namespace App\Http\Controllers\Reports;

use App\Domains\Reporting\Queries\VelocityBySprintQuery;
use App\Http\Controllers\Controller;

class SprintVelocityController extends Controller
{
    public function json(VelocityBySprintQuery $query)
    {
        return response()->json([
            'rows' => $query->run()->values(),
        ]);
    }

    public function csv(VelocityBySprintQuery $query)
    {
        $rows = $query->run();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
                fputcsv($out, [
                    'sprint_id',
                    'sprint_name',
                    'starts_at',
                    'ends_at',
                    'closed_at',
                    'scope_points',
                    'completed_points',
                    'remaining_points',
                    'remake_cards_count',
                    'remake_points_raw',
                    'remake_points_adjusted',
                ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['sprint_id'],
                    $r['sprint_name'],
                    $r['starts_at'],
                    $r['ends_at'],
                    $r['closed_at'],
                    $r['scope_points'],
                    $r['completed_points'],
                    $r['remaining_points'],
                    $r['remake_cards_count'],
                    $r['remake_points_raw'],
                    $r['remake_points_adjusted'],
                ]);
            }

            fclose($out);
        }, 'velocity_by_sprint.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
