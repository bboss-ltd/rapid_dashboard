<?php

namespace App\Domains\Reporting\Repositories;

use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Collection;

final class ReportScheduleRepository
{
    public function listAll(): Collection
    {
        return ReportSchedule::query()->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ReportSchedule
    {
        return ReportSchedule::create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ReportSchedule $schedule, array $data): ReportSchedule
    {
        $schedule->update($data);

        return $schedule;
    }

    public function delete(ReportSchedule $schedule): void
    {
        $schedule->delete();
    }
}
