<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SprintCreate extends Command
{
    protected $signature = 'sprint:create
        {name}
        {boardId}
        {startsAt}
        {endsAt}
        {doneListIds* : One or more Trello list IDs that count as Done}';

    protected $description = 'Create a Sprint linked to a Trello board-per-sprint';

    public function handle(TrelloClient $trello): int
    {
        $name = (string) $this->argument('name');
        $boardId = (string) $this->argument('boardId');
        $startsAt = Carbon::parse((string) $this->argument('startsAt'));
        $endsAt = Carbon::parse((string) $this->argument('endsAt'));
        $doneListIds = (array) $this->argument('doneListIds');
        $trello->get("/boards/{$boardId}", ['fields' => 'name']);

        $sprint = Sprint::create([
            'name' => $name,
            'trello_board_id' => $boardId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'done_list_ids' => $doneListIds,
        ]);

        $this->info("Created sprint {$sprint->id}: {$sprint->name}");
        return self::SUCCESS;
    }
}
