<?php

namespace App\Console\Commands;

use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use App\Models\Sprint;
use Illuminate\Console\Command;

class TrelloPollSprint extends Command
{
    protected $signature = 'trello:poll-sprint {sprintId}';
    protected $description = 'Poll Trello board actions for a sprint and store them locally';

    public function handle(PollBoardActionsAction $action): int
    {
        $sprint = Sprint::findOrFail((int) $this->argument('sprintId'));
        $action->run($sprint);
        $this->info('Polled board actions.');
        return self::SUCCESS;
    }
}
