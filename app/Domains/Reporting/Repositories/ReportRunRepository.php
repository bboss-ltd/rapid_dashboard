<?php

namespace App\Domains\Reporting\Repositories;

use App\Models\ReportRun;
use Illuminate\Database\Eloquent\Collection;

final class ReportRunRepository
{
    public function listAll(): Collection
    {
        return ReportRun::query()->latest()->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ReportRun
    {
        return ReportRun::create($data);
    }
}
