<?php

namespace App\Console\Commands;

use App\Domains\Sprints\Actions\DetectAndCloseSprintAction;
use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Domains\Sprints\Actions\SyncSprintBoardMetadataAction;
use App\Domains\Sprints\Actions\SyncSprintRegistryAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Domains\Sprints\Policies\ShouldReconcileSprintPolicy;
use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use App\Models\Sprint;
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
    ): int {
        $count = $syncRegistry->handle();
        $this->info("Synced {$count} sprint(s) from the registry.");

        $sprints = Sprint::query()
            ->orderBy('starts_at')
            ->get();

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

        $active = $this->determineActiveSprint($sprints);

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

        $hasStart = $active->snapshots()->where('type', 'start')->exists();
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
            $latestAdHoc = $active->snapshots()->where('type', 'ad_hoc')->latest('taken_at')->first();
            $shouldTake = !$latestAdHoc || $latestAdHoc->taken_at->diffInMinutes(now()) >= $minMinutes;

            if ($shouldTake) {
                $takeSnapshot->run($active, 'ad_hoc', 'auto');
                $this->info('Ad hoc snapshot created.');
            }
        }

        return self::SUCCESS;
    }

    private function determineActiveSprint($sprints): ?Sprint
    {
        $activeByStatus = $sprints
            ->where('status', 'active')
            ->whereNull('closed_at');
        if ($activeByStatus->count() === 1) {
            return $activeByStatus->first();
        }

        $now = now();
        $activeByDate = $sprints->filter(function (Sprint $sprint) use ($now) {
            return !$sprint->isClosed()
                && $sprint->starts_at
                && $sprint->ends_at
                && $sprint->starts_at <= $now
                && $sprint->ends_at >= $now;
        });

        if ($activeByDate->count() === 1) {
            return $activeByDate->first();
        }

        if ($activeByDate->count() > 1) {
            return $activeByDate->sortByDesc('starts_at')->first();
        }

        return null;
    }
}
