<?php

namespace App\Console\Commands;

use App\Domains\Sprints\Actions\DetectAndCloseSprintAction;
use App\Domains\Sprints\Actions\DetermineActiveSprintAction;
use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Domains\Sprints\Actions\SyncSprintBoardMetadataAction;
use App\Domains\Sprints\Actions\SyncSprintRegistryAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Domains\Sprints\Policies\ShouldReconcileSprintPolicy;
use App\Domains\Sprints\Repositories\SprintRepository;
use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use Illuminate\Console\Command;

class RunDashboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trello:run-dashboard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Master command to sync Trello sprint registry and refresh wallboard data';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncSprintRegistryAction $syncRegistry,
        SyncSprintBoardMetadataAction $syncBoard,
        DetectAndCloseSprintAction $detectClose,
        ReconcileSprintBoardStateAction $reconcile,
        TakeSprintSnapshotAction $takeSnapshot,
        ShouldReconcileSprintPolicy $shouldReconcile,
        PollBoardActionsAction $pollActions,
        DetermineActiveSprintAction $determineActiveSprint,
        SprintRepository $sprintsRepo,
        SprintSnapshotRepository $snapshotsRepo,
    ): int {
        $count = $syncRegistry->handle();
        $this->info("Synced {$count} sprint(s) from the registry.");

        $sprints = $sprintsRepo->listAllByStartAsc();

        if ($sprints->isEmpty()) {
            $this->warn('No sprints found after sync. Check TRELLO_REGISTRY_BOARD_ID and custom field IDs.');
            return self::FAILURE;
        }

        $openSprints = $sprints->whereNull('closed_at');

        foreach ($openSprints as $sprint) {
            $pollActions->run($sprint);
            $syncBoard->run($sprint);
            $detectClose->run($sprint);
        }

        $active = $determineActiveSprint->run($sprints);

        if (!$active) {
            $this->warn('No active sprint found (by status or date).');
            $upcoming = $sprints
                ->whereNull('closed_at')
                ->where('starts_at', '>', now())
                ->take(3);

            foreach ($upcoming as $s) {
                $this->line("Upcoming: {$s->name} ({$s->starts_at?->toDateString()} to {$s->ends_at?->toDateString()})");
            }

            return self::SUCCESS;
        }

        $this->info("Active sprint: {$active->name} (#{$active->id})");

        $hasStart = $snapshotsRepo->hasSnapshotType($active, 'start');
        if (!$hasStart) {
            $takeSnapshot->run($active, 'start', 'auto');
            $this->info('Start snapshot created.');
        }

        if ($shouldReconcile->check($active)) {
            $reconcileSnap = $reconcile->run($active);
            if ($reconcileSnap) {
                $this->info('Reconcile snapshot created.');
            }
        }

        if ((bool) config('trello_sync.take_ad_hoc_snapshots', true)) {
            $minMinutes = (int) config('trello_sync.ad_hoc_snapshot_every_minutes', 60);
            $latestAdHoc = $snapshotsRepo->latestByType($active, 'ad_hoc');
            $shouldTake = !$latestAdHoc || $latestAdHoc->taken_at->diffInMinutes(now()) >= $minMinutes;

            if ($shouldTake) {
                $takeSnapshot->run($active, 'ad_hoc', 'auto');
                $this->info('Ad hoc snapshot created.');
            }
        }

        return self::SUCCESS;
    }
}
