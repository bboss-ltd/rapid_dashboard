<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Actions\ListReportDefinitionsAction;
use App\Models\ReportDefinition;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportDefinitionController extends Controller
{
    public function index(Request $request, ListReportDefinitionsAction $listDefinitions): View
    {
        $reportDefinitions = $listDefinitions->run();

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
