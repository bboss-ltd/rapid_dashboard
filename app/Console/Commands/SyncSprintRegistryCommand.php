<?php

namespace App\Console\Commands;

use App\Domains\Sprints\Actions\SyncSprintRegistryAction;
use Illuminate\Console\Command;

class SyncSprintRegistryCommand extends Command
{
    protected $signature = 'trello:sprints:sync-registry';
    protected $description = 'Sync sprint registry control cards from Trello into local sprints table';

    public function handle(SyncSprintRegistryAction $action): int
    {
        $count = $action->handle();

        $this->info("Synced {$count} sprint control card(s).");
        return self::SUCCESS;
    }
}
