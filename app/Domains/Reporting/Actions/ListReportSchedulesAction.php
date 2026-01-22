<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportScheduleRepository;
use Illuminate\Database\Eloquent\Collection;

final class ListReportSchedulesAction
{
    public function __construct(private ReportScheduleRepository $schedules) {}

    public function run(): Collection
    {
        return $this->schedules->listAll();
    }
}
