<?php

namespace App\Http\Controllers\Reports;

use App\Domains\Reporting\Actions\GetSprintRolloverReportAction;
use App\Http\Controllers\Controller;
use App\Models\Sprint;

class SprintRolloverController extends Controller
{
    /**
     * GET /reports/sprints/{sprint}/rollover.json
     */
    public function json(Sprint $sprint, GetSprintRolloverReportAction $report)
    {
        return response()->json($report->run($sprint));
    }

    /**
     * GET /reports/sprints/{sprint}/rollover.csv
     */
    public function csv(Sprint $sprint, GetSprintRolloverReportAction $report)
    {
        $data = $report->run($sprint);
        $rows = $data['rollover'] ?? [];

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
