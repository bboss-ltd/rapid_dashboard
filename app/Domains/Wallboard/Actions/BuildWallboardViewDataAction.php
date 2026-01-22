<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;

final class BuildWallboardViewDataAction
{
    public function __construct(
        private SprintSummaryQuery $summaryQuery,
        private BurndownSeriesQuery $burndownQuery,
        private RemakeStatsRepository $remakeStats,
    ) {}

    /**
     * @param array<int, string> $types
     * @return array<string, mixed>
     */
    public function run(Sprint $sprint, array $types): array
    {
        $summary = $this->summaryQuery->run($sprint);
        $series = $this->burndownQuery->run($sprint, $types);
        $latestPoint = $series->last();
        $remakeStatsData = $this->remakeStats->buildRemakeStats($sprint, $types);
        $remakeReasonStats = $this->remakeStats->buildRemakeReasonStats($sprint);

        return [
            'sprint' => $sprint,
            'summary' => $summary,
            'series' => $series->values(),
            'latestPoint' => $latestPoint,
            'remakeStats' => $remakeStatsData,
            'remakeReasonStats' => $remakeReasonStats,
            'refreshSeconds' => 60,
        ];
    }
}
