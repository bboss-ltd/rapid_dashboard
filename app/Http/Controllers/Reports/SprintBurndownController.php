<?php

namespace App\Http\Controllers\Reports;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Http\Controllers\Controller;
use App\Models\Sprint;
use Illuminate\Http\Request;

class SprintBurndownController extends Controller
{
    /**
     * GET /reports/sprints/{sprint}/burndown.json?types=ad_hoc,end
     */
    public function json(Sprint $sprint, Request $request, BurndownSeriesQuery $query)
    {
        $types = $this->parseTypes($request);

        $series = $query->run($sprint, $types);

        if ($series->isEmpty()) {
            abort(404, 'No snapshots found for this sprint (for requested types).');
        }

        return response()->json([
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'starts_at' => optional($sprint->starts_at)->toIso8601String(),
                'ends_at' => optional($sprint->ends_at)->toIso8601String(),
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
            ],
            'types' => $types,
            'series' => $series->values(),
        ]);
    }

    /**
     * GET /reports/sprints/{sprint}/burndown.csv?types=ad_hoc,end
     */
    public function csv(Sprint $sprint, Request $request, BurndownSeriesQuery $query)
    {
        $types = $this->parseTypes($request);

        $series = $query->run($sprint, $types);

        if ($series->isEmpty()) {
            abort(404, 'No snapshots found for this sprint (for requested types).');
        }

        $filename = 'burndown_sprint_'.$sprint->id.'.csv';

        return response()->streamDownload(function () use ($series) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['taken_at', 'scope_points', 'done_points', 'remaining_points']);

            foreach ($series as $row) {
                fputcsv($out, [
                    $row['taken_at'],
                    $row['scope_points'],
                    $row['done_points'],
                    $row['remaining_points'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parseTypes(Request $request): array
    {
        $typesParam = (string) $request->query('types', 'ad_hoc,end');

        $types = array_values(array_filter(array_map('trim', explode(',', $typesParam))));

        // Basic safety: only allow known types
        $allowed = ['start', 'end', 'ad_hoc'];
        $types = array_values(array_intersect($types, $allowed));

        return $types ?: ['ad_hoc', 'end'];
    }
}
