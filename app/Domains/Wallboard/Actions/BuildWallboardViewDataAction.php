<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use App\Services\FourJaw\FourJawService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
        $reasonStart = now()->startOfDay();
        $reasonEnd = now()->endOfDay();
        $remakeReasonStats = $this->remakeStats->buildRemakeReasonStats($sprint, $reasonStart, $reasonEnd);
        $machines = [];
        $utilisationSummary = [
            'total_percent' => null,
            'per_machine' => [],
            'range' => null,
            'per_machine_range' => null,
        ];

        try {
            $machines = $this->fourjaw->getCurrentStatuses();
        } catch (Throwable $e) {
            $machines = [];
            Log::warning('FourJaw machine status fetch failed', [
                'sprint_id' => $sprint->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $machineIds = $this->fourjaw->getMachineIds();
            $utilCfg = config('wallboard.utilisation', []);
            $summaryDays = (int) ($utilCfg['summary_days'] ?? 7);
            $summaryShifts = (string) ($utilCfg['summary_shifts'] ?? 'on_shift');
            $machineShifts = (string) ($utilCfg['per_machine_shifts'] ?? 'on_shift');
            $debugUtil = (bool) ($utilCfg['debug'] ?? false);

            [$summaryStart, $summaryEnd] = $this->lastWorkingDaysRange($summaryDays);
            $summary = $this->fourjaw->getUtilisationSummary($summaryStart, $summaryEnd, $machineIds, $summaryShifts);

            $assetAverages = Arr::get($summary, 'asset_averages', []);
            $perMachine = [];
            if (is_array($assetAverages)) {
                foreach ($assetAverages as $row) {
                    $assetId = Arr::get($row, 'asset_id');
                    if (!is_string($assetId) || $assetId === '') {
                        continue;
                    }
                    $perMachine[$assetId] = Arr::get($row, 'utilisation_percent');
                }
            }

            $dayStart = now()->startOfDay();
            $dayEnd = now();
            $daySummary = $this->fourjaw->getUtilisationSummary($dayStart, $dayEnd, $machineIds, $machineShifts);

            $dayAssetAverages = Arr::get($daySummary, 'asset_averages', []);
            $dayPerMachine = [];
            if (is_array($dayAssetAverages)) {
                foreach ($dayAssetAverages as $row) {
                    $assetId = Arr::get($row, 'asset_id');
                    if (!is_string($assetId) || $assetId === '') {
                        continue;
                    }
                    $dayPerMachine[$assetId] = Arr::get($row, 'utilisation_percent');
                }
            }

            $utilisationSummary = [
                'total_percent' => Arr::get($summary, 'total_utilisation_percent'),
                'per_machine' => $dayPerMachine,
                'range' => [
                    'start' => $summaryStart->toIso8601String(),
                    'end' => $summaryEnd->toIso8601String(),
                ],
                'per_machine_range' => [
                    'start' => $dayStart->toIso8601String(),
                    'end' => $dayEnd->toIso8601String(),
                ],
            ];

            if ($debugUtil) {
                Log::info('FourJaw utilisation summary debug', [
                    'sprint_id' => $sprint->id,
                    'summary_range' => $utilisationSummary['range'],
                    'summary_shifts' => $summaryShifts,
                    'summary_total_percent' => $utilisationSummary['total_percent'],
                    'summary_assets' => is_array($assetAverages) ? count($assetAverages) : 0,
                    'machine_range' => $utilisationSummary['per_machine_range'],
                    'machine_shifts' => $machineShifts,
                    'machine_assets' => is_array($dayAssetAverages) ? count($dayAssetAverages) : 0,
                    'machine_ids' => $machineIds,
                ]);
            }
        } catch (Throwable $e) {
            $utilisationSummary = [
                'total_percent' => null,
                'per_machine' => [],
                'range' => null,
                'per_machine_range' => null,
            ];
            Log::warning('FourJaw utilisation fetch failed', [
                'sprint_id' => $sprint->id,
                'error' => $e->getMessage(),
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
            'utilisation' => $utilisationSummary,
            'refreshSeconds' => 60,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function lastWorkingDaysRange(int $days): array
    {
        if ($days <= 0) {
            $days = 7;
        }
        $workingDays = config('wallboard.burndown.working_days', [1, 2, 3, 4, 5]);
        $workingDays = array_map('intval', is_array($workingDays) ? $workingDays : []);

        $cursor = now()->subDay()->startOfDay();
        while (!in_array($cursor->isoWeekday(), $workingDays, true)) {
            $cursor->subDay();
        }

        $endDay = $cursor->copy();
        $count = 1;

        while ($count < $days) {
            $cursor->subDay();
            if (!in_array($cursor->isoWeekday(), $workingDays, true)) {
                continue;
            }
            $count++;
        }

        $start = $cursor->copy()->startOfDay();
        $end = $endDay->copy()->endOfDay();

        return [$start, $end];
    }
}
