<?php

namespace App\Console\Commands;

use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Models\Sprint;
use Illuminate\Console\Command;

class SprintSnapshotTake extends Command
{
    protected $signature = 'sprint:snapshot {sprintId} {type=start|end|ad_hoc}';
    protected $description = 'Take a sprint snapshot (start/end/ad_hoc)';

    public function handle(TakeSprintSnapshotAction $action): int
    {
        $sprint = Sprint::findOrFail((int) $this->argument('sprintId'));
        $type = (string) $this->argument('type');

        $snapshot = $action->run($sprint, $type, 'manual');
        $this->info("Snapshot taken: {$snapshot->id}");
        return self::SUCCESS;
    }
}
