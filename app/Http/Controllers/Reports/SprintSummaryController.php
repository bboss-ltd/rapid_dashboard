<?php

namespace App\Http\Controllers\Reports;

use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Http\Controllers\Controller;
use App\Models\Sprint;

class SprintSummaryController extends Controller
{
    /**
     * GET /reports/sprints/{sprint}/summary.json
     */
    public function json(Sprint $sprint, SprintSummaryQuery $query)
    {
        $data = $query->run($sprint);

        if (($data['has_end_snapshot'] ?? false) === false) {
            abort(404, $data['message'] ?? 'No end snapshot found for this sprint.');
        }

        return response()->json($data);
    }
}
