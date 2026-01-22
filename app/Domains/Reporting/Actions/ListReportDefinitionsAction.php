<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Repositories\ReportDefinitionRepository;
use Illuminate\Database\Eloquent\Collection;

final class ListReportDefinitionsAction
{
    public function __construct(private ReportDefinitionRepository $definitions) {}

    public function run(): Collection
    {
        return $this->definitions->listAll();
    }
}
