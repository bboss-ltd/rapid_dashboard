<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use Illuminate\Support\Collection;

final class DetermineActiveSprintAction
{
    /**
     * @param Collection<int, Sprint> $sprints
     */
    public function run(Collection $sprints): ?Sprint
    {
        $activeByStatus = $sprints
            ->where('status', 'active')
            ->whereNull('closed_at');

        if ($activeByStatus->count() === 1) {
            return $activeByStatus->first();
        }

        $now = now();
        $activeByDate = $sprints->filter(function (Sprint $sprint) use ($now) {
            return ! $sprint->isClosed()
                && $sprint->starts_at
                && $sprint->ends_at
                && $sprint->starts_at <= $now
                && $sprint->ends_at >= $now;
        });

        if ($activeByDate->count() === 1) {
            return $activeByDate->first();
        }

        if ($activeByDate->count() > 1) {
            return $activeByDate->sortByDesc('starts_at')->first();
        }

        return null;
    }
}
