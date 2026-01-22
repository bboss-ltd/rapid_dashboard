<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportRunRepository;
use App\Models\ReportRun;

final class CreateReportRunAction
{
    public function __construct(private ReportRunRepository $runs) {}

    /**
     * @param array<string, mixed> $data
     */
    public function run(array $data): ReportRun
    {
        return $this->runs->create($data);
    }
}
