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
    ): array {
        $factories = $this->buildFactoryCards();

        [$rows, $totalDays] = $this->buildReasonTable($start, $end, $perPage, $page, $queryParams);

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
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $order = $this->remakeStats->reasonFlow();
        $flowMap = $this->remakeStats->reasonFlowMap();

        $rows = DB::table('sprint_remakes')
            ->whereBetween('first_seen_at', [$start, $end])
            ->whereNull('removed_at')
            ->selectRaw('DATE(first_seen_at) as day, reason_label, label_name, COUNT(*) as total')
            ->groupBy('day', 'reason_label', 'label_name')
            ->get();

        $countsByDay = [];

        foreach ($rows as $row) {
            if ($this->remakeStats->isRemoveLabel($row->label_name ?? null)) {
                continue;
            }

            $day = (string) ($row->day ?? '');
            if ($day === '') {
                continue;
            }

            $reasonKey = $this->remakeStats->reasonKey($row->reason_label ?? null);
            $label = $reasonKey === null ? 'Unlabelled' : ($flowMap[$reasonKey] ?? null);
            if (!$label) {
                continue;
            }

            $countsByDay[$day][$label] = ($countsByDay[$day][$label] ?? 0) + (int) ($row->total ?? 0);
        }

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

        $tableRows = [];
        foreach ($pageDays as $day) {
            $row = [
                'day' => $day,
                'counts' => [],
            ];
            foreach ($order as $label) {
                $row['counts'][$label] = $countsByDay[$day][$label] ?? 0;
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
