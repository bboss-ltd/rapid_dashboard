<?php

namespace App\Console\Commands;

use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Models\Sprint;
use Illuminate\Console\Command;

class SprintReconcile extends Command
{
    protected $signature = 'sprint:reconcile {sprintId}';
    protected $description = 'Reconcile sprint board state against latest snapshot; create reconcile snapshot if drift detected';

    public function handle(ReconcileSprintBoardStateAction $action): int
    {
        $sprint = Sprint::findOrFail((int) $this->argument('sprintId'));

        $snapshot = $action->run($sprint);

        if (!$snapshot) {
            $this->info('No drift detected. No snapshot created.');
            return self::SUCCESS;
        }

        $this->info("Reconcile snapshot created: {$snapshot->id}");
        $this->line('Meta: ' . json_encode($snapshot->meta, JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
