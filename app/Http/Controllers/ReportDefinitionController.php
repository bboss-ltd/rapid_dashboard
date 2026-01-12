<?php

namespace App\Http\Controllers;

use App\Models\ReportDefinition;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportDefinitionController extends Controller
{
    public function index(Request $request): View
    {
        $reportDefinitions = ReportDefinition::all();

        return view('reportDefinition.index', [
            'reportDefinitions' => $reportDefinitions,
        ]);
    }

    public function show(Request $request, ReportDefinition $reportDefinition): View
    {
        return view('reportDefinition.show', [
            'reportDefinition' => $reportDefinition,
        ]);
    }
}
