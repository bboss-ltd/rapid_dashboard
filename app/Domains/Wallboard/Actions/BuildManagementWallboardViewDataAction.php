<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Services\FourJaw\FourJawService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BuildManagementWallboardViewDataAction
{
    public function __construct(
        private FourJawService $fourjaw,
        private RemakeStatsRepository $remakeStats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        Carbon $start,
        Carbon $end,
        int $perPage,
        int $page,
        array $queryParams = [],
        string $highlightMode = 'max',
        int $deltaDays = 1,
    ): array {
        $factories = $this->buildFactoryCards();

        [$rows, $totalDays] = $this->buildReasonTable($start, $end, $perPage, $page, $queryParams, $highlightMode, $deltaDays);

        return [
            'factories' => $factories,
            'reasonRows' => $rows,
            'totalDays' => $totalDays,
            'reasonOrder' => $this->remakeStats->reasonFlow(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFactoryCards(): array
    {
        $factories = [];
        $statusMap = [];

        try {
            $statuses = $this->fourjaw->getCurrentStatuses();
            foreach ($statuses as $status) {
                $id = $status['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $statusMap[$id] = $status;
                }
            }
        } catch (Throwable $e) {
            Log::warning('FourJaw management status fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $assetMaps = ['assets' => [], 'parents' => []];
        try {
            $assetMaps = $this->fourjaw->getAssetMaps();
        } catch (Throwable $e) {
            Log::warning('FourJaw assets fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $assets = $assetMaps['assets'] ?? [];
        $parents = $assetMaps['parents'] ?? [];

        foreach (config('fourjaw.machines', []) as $factoryId => $machines) {
            $factoryName = null;

            if (is_array($machines) && !empty($machines)) {
                $factoryName = $machines[0]['parent_display_name'] ?? null;
            }
            if (!$factoryName && is_string($factoryId)) {
                $factoryName = $parents[$factoryId]['display_name'] ?? null;
            }
            if (!$factoryName) {
                $factoryName = 'Factory';
            }

            $factoryMachines = [];
            foreach ($machines as $machine) {
                $id = $machine['id'] ?? null;
                if (!is_string($id) || $id === '') {
                    continue;
                }

                $status = $statusMap[$id] ?? [];
                $assetName = $assets[$id]['display_name'] ?? null;
                $name = $assetName ?: ($machine['display_name'] ?? 'Unknown');

                $factoryMachines[] = array_merge($status, [
                    'id' => $id,
                    'name' => $name,
                ]);
            }

            $factories[] = [
                'id' => $factoryId,
                'name' => $factoryName,
                'machines' => $factoryMachines,
            ];
        }

        return $factories;
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: int}
     */
    private function buildReasonTable(
        Carbon $start,
        Carbon $end,
        int $perPage,
        int $page,
        array $queryParams = [],
        string $highlightMode = 'max',
        int $deltaDays = 1,
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $order = $this->remakeStats->reasonFlow();
        $flowMap = $this->remakeStats->reasonFlowMap();

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }
        $days = array_reverse($days);

        $totalDays = count($days);
        $offset = max(0, ($page - 1) * $perPage);
        $pageDays = array_slice($days, $offset, $perPage);

        $fetchCountsForDay = function (string $day) use ($flowMap, $order) {
            $dayStart = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();

            $rows = DB::table('sprint_remakes')
                ->whereBetween('first_seen_at', [$dayStart, $dayEnd])
                ->whereNull('removed_at')
                ->selectRaw('reason_label, label_name, COUNT(*) as total')
                ->groupBy('reason_label', 'label_name')
                ->get();

            $counts = [];
            foreach ($order as $label) {
                $counts[$label] = 0;
            }

            foreach ($rows as $entry) {
                if ($this->remakeStats->isRemoveLabel($entry->label_name ?? null)) {
                    continue;
                }
                $reasonKey = $this->remakeStats->reasonKey($entry->reason_label ?? null);
                $label = $reasonKey === null ? 'Unlabelled' : ($flowMap[$reasonKey] ?? null);
                if (!$label) {
                    continue;
                }
                $counts[$label] = ($counts[$label] ?? 0) + (int) ($entry->total ?? 0);
            }

            return $counts;
        };

        $tableRows = [];
        foreach ($pageDays as $day) {
            $counts = $fetchCountsForDay($day);
            $row = [
                'day' => $day,
                'counts' => [],
                'highlights' => [],
                'total' => 0,
            ];
            foreach ($order as $label) {
                $count = $counts[$label] ?? 0;
                $row['counts'][$label] = $count;
                $row['total'] += $count;
            }

            if ($highlightMode === 'delta') {
                $compareDay = Carbon::createFromFormat('Y-m-d', $day)->subDays($deltaDays)->toDateString();
                $prevCounts = $compareDay !== $day ? $fetchCountsForDay($compareDay) : [];
                $deltas = [];
                foreach ($order as $label) {
                    $current = $row['counts'][$label] ?? 0;
                    $prev = $prevCounts[$label] ?? 0;
                    $deltas[$label] = $current - $prev;
                }
                $maxDelta = max($deltas ?: [0]);
                if ($maxDelta > 0) {
                    foreach ($deltas as $label => $delta) {
                        if ($delta === $maxDelta) {
                            $row['highlights'][$label] = true;
                        }
                    }
                }
            } else {
                $max = max($row['counts'] ?: [0]);
                if ($max > 0) {
                    foreach ($row['counts'] as $label => $count) {
                        if ($count === $max) {
                            $row['highlights'][$label] = true;
                        }
                    }
                }
            }

            $tableRows[] = $row;
        }

        $paginator = new LengthAwarePaginator(
            $tableRows,
            $totalDays,
            $perPage,
            $page,
            [
                'path' => $queryParams['path'] ?? request()->url(),
                'query' => $queryParams,
            ]
        );

        return [$paginator, $totalDays];
    }
}
