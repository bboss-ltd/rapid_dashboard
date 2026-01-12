<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRunStoreRequest;
use App\Models\ReportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportRunController extends Controller
{
    public function index(Request $request): View
    {
        $reportRuns = ReportRun::all();

        return view('report-run.index', [
            'reportRuns' => $reportRuns,
        ]);
    }

    public function create(Request $request): View
    {
        return view('report-run.create');
    }

    public function store(ReportRunStoreRequest $request): RedirectResponse
    {
        $reportRun = ReportRun::create($request->validated());

        $request->session()->flash('reportRun.id', $reportRun->id);

        return redirect()->route('reportRun.index');
    }

    public function show(Request $request, ReportRun $reportRun): View
    {
        return view('report-run.show', [
            'reportRun' => $reportRun,
        ]);
    }
}
