<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Sprints\Repositories\SprintRepository;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Collection;

final class ResolveWallboardSprintAction
{
    public function __construct(private SprintRepository $sprints) {}

    /**
     * @return array{mode: 'redirect'|'empty', sprint?: Sprint, next?: Collection}
     */
    public function run(): array
    {
        $activeByStatus = $this->sprints->listActiveByStatus();
        if ($activeByStatus->count() === 1) {
            return ['mode' => 'redirect', 'sprint' => $activeByStatus->first()];
        }

        $activeByDates = $this->sprints->findActiveByDates();
        if ($activeByDates) {
            return ['mode' => 'redirect', 'sprint' => $activeByDates];
        }

        $open = $this->sprints->findLatestOpen();
        if ($open) {
            return ['mode' => 'redirect', 'sprint' => $open];
        }

        return ['mode' => 'empty', 'next' => $this->sprints->findUpcoming(3)];
    }
}
