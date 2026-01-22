<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Models\Sprint;

final class GetSprintDetailsAction
{
    public function __construct(
        private SprintSnapshotRepository $snapshots,
        private BurndownSeriesQuery $burndown,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(Sprint $sprint): array
    {
        $snapshots = $this->snapshots->paginatedBySprint($sprint, 10);
        $latest = $this->snapshots->latest($sprint);

        $latestCards = $latest
            ? $this->snapshots->paginatedCards($latest, 25, 'cardsPage')
            : null;

        $series = $this->burndown->run($sprint, ['ad_hoc', 'end']);
        $latestPoint = $series->last();

        return [
            'sprint' => $sprint,
            'snapshots' => $snapshots,
            'latestSnapshot' => $latest,
            'latestSnapshotCards' => $latestCards,
            'latestPoint' => $latestPoint,
        ];
    }
}
