<?php

namespace App\Domains\Wallboard\Actions;

use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use App\Domains\Wallboard\DTOs\WallboardSyncResult;
use App\Domains\Wallboard\Events\WallboardSynced;
use App\Models\Sprint;

final class SyncWallboardAction
{
    public function __construct(
        private PollBoardActionsAction $pollActions,
        private ReconcileSprintBoardStateAction $reconcile,
        private TakeSprintSnapshotAction $takeSnapshot,
    ) {}

    public function run(Sprint $sprint): WallboardSyncResult
    {
        $this->pollActions->run($sprint);
        $reconcileSnap = $this->reconcile->run($sprint);
        $snap = $this->takeSnapshot->run($sprint, 'ad_hoc', 'wallboard');

        event(new WallboardSynced($sprint, $snap, $reconcileSnap));

        return new WallboardSyncResult($snap, $reconcileSnap);
    }
}
