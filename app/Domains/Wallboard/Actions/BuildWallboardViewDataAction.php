<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use App\Services\FourJaw\FourJawService;
use Throwable;
use Illuminate\Support\Facades\Log;

final class BuildWallboardViewDataAction
{
    public function __construct(
        private SprintSummaryQuery $summaryQuery,
        private BurndownSeriesQuery $burndownQuery,
        private RemakeStatsRepository $remakeStats,
        private FourJawService $fourjaw,
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
        $machines = [];

        try {
            $machines = $this->fourjaw->getCurrentStatuses();
        } catch (Throwable) {
            $machines = [];
            Log::warning('FourJaw machine status fetch failed', [
                'sprint_id' => $sprint->id,
            ]);
        }

        return [
            'sprint' => $sprint,
            'summary' => $summary,
            'series' => $series->values(),
            'latestPoint' => $latestPoint,
            'remakeStats' => $remakeStatsData,
            'remakeReasonStats' => $remakeReasonStats,
            'machines' => $machines,
            'refreshSeconds' => 60,
        ];
    }
}
