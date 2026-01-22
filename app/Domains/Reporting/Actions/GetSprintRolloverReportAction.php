<?php

namespace App\Domains\Reporting\Actions;

use App\Domains\Reporting\Queries\RolloverCardsQuery;
use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Models\Sprint;

final class GetSprintRolloverReportAction
{
    public function __construct(
        private SprintSnapshotRepository $snapshots,
        private RolloverCardsQuery $query,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(Sprint $sprint): array
    {
        if (! $this->snapshots->hasSnapshotType($sprint, 'end')) {
            abort(404, 'No end snapshot found for this sprint yet.');
        }

        $rows = $this->query->run($sprint);

        return [
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
            ],
            'rollover' => $rows,
            'count' => count($rows),
        ];
    }
}
