<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportScheduleRepository;
use App\Models\ReportSchedule;

final class CreateReportScheduleAction
{
    public function __construct(private ReportScheduleRepository $schedules) {}

    /**
     * @param array<string, mixed> $data
     */
    public function run(array $data): ReportSchedule
    {
        return $this->schedules->create($data);
    }
}
