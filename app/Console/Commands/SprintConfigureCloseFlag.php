<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use Illuminate\Console\Command;

class SprintConfigureCloseFlag extends Command
{
    protected $signature = 'sprint:configure-close {sprintId} {controlCardId} {statusFieldId} {closedOptionId}';
    protected $description = 'Configure Trello control card + status field IDs used to detect sprint closure';

    public function handle(): int
    {
        $sprint = Sprint::findOrFail((int) $this->argument('sprintId'));

        $sprint->trello_control_card_id = (string) $this->argument('controlCardId');
        $sprint->trello_status_custom_field_id = (string) $this->argument('statusFieldId');
        $sprint->trello_closed_option_id = (string) $this->argument('closedOptionId');

        $sprint->save();

        $this->info('Sprint close flag configured.');
        return self::SUCCESS;
    }
}
