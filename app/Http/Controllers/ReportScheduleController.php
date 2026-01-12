<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportScheduleStoreRequest;
use App\Http\Requests\ReportScheduleUpdateRequest;
use App\Models\ReportSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $reportSchedules = ReportSchedule::all();

        return view('reportSchedule.index', [
            'reportSchedules' => $reportSchedules,
        ]);
    }

    public function create(Request $request): View
    {
        return view('reportSchedule.create');
    }

    public function store(ReportScheduleStoreRequest $request): RedirectResponse
    {
        $reportSchedule = ReportSchedule::create($request->validated());

        $request->session()->flash('reportSchedule.id', $reportSchedule->id);

        return redirect()->route('reportSchedules.index');
    }

    public function show(Request $request, ReportSchedule $reportSchedule): View
    {
        return view('reportSchedule.show', [
            'reportSchedule' => $reportSchedule,
        ]);
    }

    public function edit(Request $request, ReportSchedule $reportSchedule): View
    {
        return view('reportSchedule.edit', [
            'reportSchedule' => $reportSchedule,
        ]);
    }

    public function update(ReportScheduleUpdateRequest $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $reportSchedule->update($request->validated());

        $request->session()->flash('reportSchedule.id', $reportSchedule->id);

        return redirect()->route('reportSchedules.index');
    }

    public function destroy(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $reportSchedule->delete();

        return redirect()->route('reportSchedules.index');
    }
}
