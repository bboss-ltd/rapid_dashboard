<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportScheduleRepository;
use App\Models\ReportSchedule;

final class DeleteReportScheduleAction
{
    public function __construct(private ReportScheduleRepository $schedules) {}

    public function run(ReportSchedule $schedule): void
    {
        $this->schedules->delete($schedule);
    }
}
