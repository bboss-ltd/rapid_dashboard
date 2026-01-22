<?php

namespace App\Http\Controllers;

use App\Domains\Sprints\Actions\ListSprintsAction;
use App\Models\Dashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Legacy route: /dashboards
     *
     * We treat each "dashboard" as a Sprint row.
     */
    public function index(Request $request, ListSprintsAction $listSprints): View
    {
        $sprints = $listSprints->run();

        return view('dashboard.index', [
            'sprints' => $sprints,
        ]);
    }

    /**
     * Legacy route: /dashboards/{dashboard}
     *
     * Route model binding resolves Dashboard which extends Sprint.
     */
    public function show(Request $request, Dashboard $dashboard): View
    {
        return view('dashboard.show', [
            'sprint' => $dashboard,
        ]);
    }
}
