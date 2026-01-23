<?php

namespace App\Services\FourJaw;

use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class FourJawService
{
    public function __construct(private FourJawClient $fourjaw)
    {}

    /**
     * @return string[]
     */
    public function getMachineIds(): array
    {
        $machineIds = [];

        foreach (config('fourjaw.machines') as $group) {
            foreach ($group as $machine) {
                $id = $machine['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $machineIds[] = $id;
                }
            }
        }

        return $machineIds;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMachineMap(): array
    {
        $map = [];

        foreach (config('fourjaw.machines') as $group) {
            foreach ($group as $machine) {
                $id = $machine['id'] ?? null;
                if (!is_string($id) || $id === '') {
                    continue;
                }

                $map[$id] = $machine;
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentStatuses(?array $machineIds = null): array
    {
        $machineIds = $machineIds ?? $this->getMachineIds();

        if ($machineIds === []) {
            return [];
        }

        $response = $this->fourjaw->get(
            config('fourjaw.endpoints.current_status'),
            [
                'page_size' => (int) config('fourjaw.status_page_size', 200),
            ]
        );

        $items = Arr::get($response, 'items', []);
        if (!is_array($items)) {
            return [];
        }

        $map = $this->getMachineMap();
        $machineIds = array_values($machineIds);
        $allowed = array_flip($machineIds);

        $resultsById = [];

        foreach ($items as $item) {
            $id = Arr::get($item, 'id');
            if (!is_string($id) || !isset($allowed[$id])) {
                continue;
            }

            $status = Arr::get($item, 'status', []);
            $classification = strtoupper((string) Arr::get($status, 'classification', ''));
            $downtimeReason = (string) Arr::get($status, 'downtime_reason_name', '');

            $start = Arr::get($status, 'start_timestamp');
            $durationMinutes = null;
            if (is_string($start) && $start !== '') {
                $durationMinutes = Carbon::parse($start)->diffInMinutes(now(), false);
                if ($durationMinutes < 0) {
                    Log::warning('FourJaw machine status start_timestamp is in the future', [
                        'machine_id' => $id,
                        'start_timestamp' => $start,
                        'classification' => $classification,
                    ]);
                    $durationMinutes = 0;
                }
            }

            $resultsById[$id] = [
                'id' => $id,
                'name' => Arr::get($item, 'display_name')
                    ?? Arr::get($map, "{$id}.display_name"),
                'status_classification' => $classification,
                'status' => $this->normalizeStatus($classification, $downtimeReason),
                'duration_minutes' => $durationMinutes,
                'has_data' => (bool) Arr::get($item, 'has_data', false),
            ];
        }

        $results = [];
        foreach ($machineIds as $machineId) {
            if (isset($resultsById[$machineId])) {
                $results[] = $resultsById[$machineId];
                continue;
            }

            $fallback = $map[$machineId] ?? [];
            $results[] = [
                'id' => $machineId,
                'name' => $fallback['display_name'] ?? 'Unknown',
                'status_classification' => 'UNKNOWN',
                'status' => 'unknown',
                'duration_minutes' => null,
                'has_data' => false,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAssets(): array
    {
        $response = $this->fourjaw->get(config('fourjaw.endpoints.assets'), [
            'page_size' => 500,
        ]);

        $items = Arr::get($response, 'items', $response);
        return is_array($items) ? $items : [];
    }

    /**
     * @return array{assets: array<string, array<string, mixed>>, parents: array<string, array<string, mixed>>}
     */
    public function getAssetMaps(): array
    {
        $items = $this->getAssets();

        $assets = [];
        $parents = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = Arr::get($item, 'id');
            if (!is_string($id) || $id === '') {
                continue;
            }

            $displayName = (string) (Arr::get($item, 'display_name')
                ?? Arr::get($item, 'name')
                ?? '');
            $parentId = Arr::get($item, 'parent_id');
            if (!is_string($parentId) || $parentId === '') {
                $parentId = Arr::get($item, 'parent.id');
            }
            $parentName = (string) (Arr::get($item, 'parent_display_name')
                ?? Arr::get($item, 'parent.display_name')
                ?? '');

            $assets[$id] = [
                'id' => $id,
                'display_name' => trim($displayName) !== '' ? $displayName : null,
                'parent_id' => is_string($parentId) ? $parentId : null,
                'parent_display_name' => trim($parentName) !== '' ? $parentName : null,
            ];

            if (is_string($parentId) && $parentId !== '') {
                $parents[$parentId] = [
                    'id' => $parentId,
                    'display_name' => trim($parentName) !== '' ? $parentName : null,
                ];
            }
        }

        return ['assets' => $assets, 'parents' => $parents];
    }

    /**
     * @param array<int, string> $machineIds
     * @return array<string, mixed>
     */
    public function getUtilisationSummary(
        Carbon $start,
        Carbon $end,
        array $machineIds,
        string $shifts = 'on_shift'
    ): array {
        if ($machineIds === []) {
            return [];
        }

        $assetIds = implode(',', $machineIds);

        return $this->fourjaw->get(
            config('fourjaw.endpoints.utilisation_summary'),
            [
                'start_timestamp' => $start->toIso8601String(),
                'end_timestamp' => $end->toIso8601String(),
                'asset_ids' => $assetIds,
                'shifts' => $shifts,
            ]
        );
    }

    private function normalizeStatus(string $classification, string $downtimeReason): string
    {
        if ($classification === 'UPTIME') {
            return 'running';
        }

        if ($classification === 'DOWNTIME') {
            $reason = strtolower($downtimeReason);

            if (str_contains($reason, 'maint')) {
                return 'maintenance';
            }

            return 'idle';
        }

        return 'unknown';
    }

    /**
     * @param string|string[] $machineIds
     *
     * @return array<string, mixed>
     */
    public function getStatusPeriods(
        string|array $machineIds = [],
        ?string $startFrom = null,
        ?string $endAt = null
    ): array {
        @trigger_error('FourJawService::getStatusPeriods is deprecated.', E_USER_DEPRECATED);

        $now = now();

        $end = $endAt !== null
            ? Carbon::parse($endAt)
            : $now->copy();

        $start = $startFrom !== null
            ? Carbon::parse($startFrom)
            : $end->copy()->subMinute();

        $machineIds = is_string($machineIds)
            ? [$machineIds]
            : array_values($machineIds);

        if ($machineIds === []) {
            $machineIds = $this->getMachineIds();
        }

        if ($machineIds === []) {
            throw new InvalidArgumentException(
                'No machine IDs resolved. The radar is empty.'
            );
        }

        return $this->fourjaw->get('/status-periods', [
            'query' => [
                'asset_ids'       => $machineIds,
                'start_timestamp' => $start->toIso8601String(),
                'end_timestamp'   => $end->toIso8601String(),
                'order'           => 'descending',
                'page_size'       => 1,
            ],
        ]);
    }
}
