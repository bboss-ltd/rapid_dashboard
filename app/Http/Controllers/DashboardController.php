<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Legacy route: /dashboards
     *
     * We treat each "dashboard" as a Sprint row.
     */
    public function index(Request $request): View
    {
        $sprints = Sprint::query()
            ->orderByDesc('starts_at')
            ->get();

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
