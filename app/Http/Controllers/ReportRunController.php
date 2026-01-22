<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Actions\CreateReportRunAction;
use App\Domains\Reporting\Actions\ListReportRunsAction;
use App\Http\Requests\ReportRunStoreRequest;
use App\Models\ReportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportRunController extends Controller
{
    public function index(Request $request, ListReportRunsAction $listRuns): View
    {
        $reportRuns = $listRuns->run();

        return view('report-run.index', [
            'reportRuns' => $reportRuns,
        ]);
    }

    public function create(Request $request): View
    {
        return view('report-run.create');
    }

    public function store(ReportRunStoreRequest $request, CreateReportRunAction $createRun): RedirectResponse
    {
        $reportRun = $createRun->run($request->validated());

        $request->session()->flash('reportRun.id', $reportRun->id);

        return redirect()->route('report-runs.index');
    }

    public function show(Request $request, ReportRun $reportRun): View
    {
        return view('report-run.show', [
            'reportRun' => $reportRun,
        ]);
    }
}
