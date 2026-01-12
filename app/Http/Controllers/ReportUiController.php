<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Reporting\Queries\VelocityBySprintQuery;
use App\Models\Sprint;

class ReportUiController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function velocity(VelocityBySprintQuery $query)
    {
        $rows = $query->run();

        $table = [
            'title' => 'Velocity by Sprint',
            'subtitle' => 'Based on END snapshots (immutable).',
            'columns' => [
                ['key' => 'sprint_name', 'label' => 'Sprint'],
                ['key' => 'starts_at', 'label' => 'Start'],
                ['key' => 'ends_at', 'label' => 'End'],
                ['key' => 'scope_points', 'label' => 'Scope'],
                ['key' => 'completed_points', 'label' => 'Done'],
                ['key' => 'remaining_points', 'label' => 'Remaining'],
            ],
            'rows' => $rows->map(function ($r) {
                return [
                    ...$r,
                    'starts_at' => $r['starts_at'] ? substr($r['starts_at'], 0, 10) : null,
                    'ends_at' => $r['ends_at'] ? substr($r['ends_at'], 0, 10) : null,
                ];
            })->values(),
            'downloads' => [
                ['label' => 'CSV', 'href' => '/reports/velocity.csv'],
                ['label' => 'JSON', 'href' => '/reports/velocity.json'],
            ],
        ];

        return view('reports.table', compact('table'));
    }

    public function sprintSummary(Sprint $sprint, SprintSummaryQuery $query)
    {
        $data = $query->run($sprint);

        // Render “summary cards” + (optional) tables inside the view
        return view('reports.sprint-summary', [
            'sprint' => $sprint,
            'data' => $data,
        ]);
    }
}
