<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Actions\CreateReportScheduleAction;
use App\Domains\Reporting\Actions\DeleteReportScheduleAction;
use App\Domains\Reporting\Actions\ListReportSchedulesAction;
use App\Domains\Reporting\Actions\UpdateReportScheduleAction;
use App\Http\Requests\ReportScheduleStoreRequest;
use App\Http\Requests\ReportScheduleUpdateRequest;
use App\Models\ReportSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportScheduleController extends Controller
{
    public function index(Request $request, ListReportSchedulesAction $listSchedules): View
    {
        $reportSchedules = $listSchedules->run();

        return view('reportSchedule.index', [
            'reportSchedules' => $reportSchedules,
        ]);
    }

    public function create(Request $request): View
    {
        return view('reportSchedule.create');
    }

    public function store(ReportScheduleStoreRequest $request, CreateReportScheduleAction $createSchedule): RedirectResponse
    {
        $reportSchedule = $createSchedule->run($request->validated());

        $request->session()->flash('reportSchedule.id', $reportSchedule->id);

        return redirect()->route('report-schedules.index');
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

    public function update(ReportScheduleUpdateRequest $request, ReportSchedule $reportSchedule, UpdateReportScheduleAction $updateSchedule): RedirectResponse
    {
        $updateSchedule->run($reportSchedule, $request->validated());

        $request->session()->flash('reportSchedule.id', $reportSchedule->id);

        return redirect()->route('report-schedules.index');
    }

    public function destroy(Request $request, ReportSchedule $reportSchedule, DeleteReportScheduleAction $deleteSchedule): RedirectResponse
    {
        $deleteSchedule->run($reportSchedule);

        return redirect()->route('report-schedules.index');
    }
}
