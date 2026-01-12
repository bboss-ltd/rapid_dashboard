<?php

namespace App\Http\Controllers\Reports;

use App\Domains\Reporting\Queries\RolloverCardsQuery;
use App\Http\Controllers\Controller;
use App\Models\Sprint;

class SprintRolloverController extends Controller
{
    /**
     * GET /reports/sprints/{sprint}/rollover.json
     */
    public function json(Sprint $sprint, RolloverCardsQuery $query)
    {
        // Rollover definition uses END snapshot
        if ($sprint->snapshots()->where('type', 'end')->count() === 0) {
            abort(404, 'No end snapshot found for this sprint yet.');
        }

        $rows = $query->run($sprint);

        return response()->json([
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
            ],
            'rollover' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * GET /reports/sprints/{sprint}/rollover.csv
     */
    public function csv(Sprint $sprint, RolloverCardsQuery $query)
    {
        if ($sprint->snapshots()->where('type', 'end')->count() === 0) {
            abort(404, 'No end snapshot found for this sprint yet.');
        }

        $rows = $query->run($sprint);

        $filename = 'rollover_sprint_'.$sprint->id.'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['trello_card_id', 'name', 'estimate_points', 'trello_list_id']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['trello_card_id'],
                    $r['name'],
                    $r['estimate_points'],
                    $r['trello_list_id'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
