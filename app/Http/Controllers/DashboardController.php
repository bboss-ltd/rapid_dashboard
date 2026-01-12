<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $dashboards = Dashboard::all();

        return view('dashboard.index', [
            'sprints' => $sprints,
        ]);
    }

    public function show(Request $request, Dashboard $dashboard): View
    {
        $sprint = Sprint::find($id);

        return view('dashboard.show', [
            'sprint' => $sprint,
        ]);
    }
}
