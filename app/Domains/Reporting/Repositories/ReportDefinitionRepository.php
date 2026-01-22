<?php

namespace App\Domains\Reporting\Repositories;

use App\Models\ReportDefinition;
use Illuminate\Database\Eloquent\Collection;

final class ReportDefinitionRepository
{
    public function listAll(): Collection
    {
        return ReportDefinition::query()->get();
    }
}
