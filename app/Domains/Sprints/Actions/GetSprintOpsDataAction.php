<?php

namespace App\Domains\Sprints\Actions;

use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Models\Sprint;

final class GetSprintOpsDataAction
{
    public function __construct(private SprintSnapshotRepository $snapshots) {}

    /**
     * @return array<string, mixed>
     */
    public function run(Sprint $sprint): array
    {
        $latestAdHoc = $this->snapshots->latestByType($sprint, 'ad_hoc');
        $latestEnd = $this->snapshots->latestByType($sprint, 'end');

        return [
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'trello_board_id' => $sprint->trello_board_id,
                'closed_at' => optional($sprint->closed_at)->toIso8601String(),
            ],
            'snapshots' => [
                'latest_ad_hoc' => $latestAdHoc ? [
                    'id' => $latestAdHoc->id,
                    'taken_at' => $latestAdHoc->taken_at->toIso8601String(),
                    'source' => $latestAdHoc->source,
                    'meta' => $latestAdHoc->meta,
                ] : null,
                'latest_end' => $latestEnd ? [
                    'id' => $latestEnd->id,
                    'taken_at' => $latestEnd->taken_at->toIso8601String(),
                    'source' => $latestEnd->source,
                    'meta' => $latestEnd->meta,
                ] : null,
            ],
        ];
    }
}
