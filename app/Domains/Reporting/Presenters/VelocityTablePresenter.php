<?php

namespace App\Domains\Reporting\Presenters;

use Illuminate\Support\Collection;

final class VelocityTablePresenter
{
    public function columns(): array
    {
        return [
            ['key' => 'sprint_name', 'label' => 'Sprint', 'sortable' => true],
            ['key' => 'starts_at', 'label' => 'Start', 'sortable' => true, 'type' => 'date'],
            ['key' => 'ends_at', 'label' => 'End', 'sortable' => true, 'type' => 'date'],
            ['key' => 'scope_points', 'label' => 'Scope', 'sortable' => true, 'type' => 'int'],
            ['key' => 'completed_points', 'label' => 'Done', 'sortable' => true, 'type' => 'int'],
            ['key' => 'remaining_points', 'label' => 'Remaining', 'sortable' => true, 'type' => 'int'],
        ];
    }

    public function normalize(Collection $rows): Collection
    {
        // Keep data raw; formatting happens in Blade components.
        return $rows;
    }
}
