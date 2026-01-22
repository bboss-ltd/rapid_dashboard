<?php

namespace App\Services\FourJaw;

use Illuminate\Support\Carbon;
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

        foreach (config('fourjaw.machines') as $machine) {
            $machineIds[] = $machine['id'];
        }

        return $machineIds;
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
