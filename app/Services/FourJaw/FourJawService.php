<?php

namespace App\Services\FourJaw;

use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;

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
                $durationMinutes = now()->diffInMinutes(Carbon::parse($start));
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
