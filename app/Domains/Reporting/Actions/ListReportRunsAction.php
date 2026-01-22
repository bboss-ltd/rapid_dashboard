<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportRunRepository;
use Illuminate\Database\Eloquent\Collection;

final class ListReportRunsAction
{
    public function __construct(private ReportRunRepository $runs) {}

    public function run(): Collection
    {
        return $this->runs->listAll();
    }
}
