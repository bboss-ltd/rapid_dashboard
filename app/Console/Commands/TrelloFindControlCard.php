<?php

namespace App\Console\Commands;

use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;

class TrelloFindControlCard extends Command
{
    protected $signature = 'trello:find-control-card {boardId} {--name=Sprint Control}';
    protected $description = 'Find a control card on a board by name and print its card ID';

    public function handle(TrelloClient $trello): int
    {
        $boardId = (string) $this->argument('boardId');
        $name = (string) $this->option('name');

        $cards = $trello->get("/boards/{$boardId}/cards", [
            'fields' => 'name',
        ]);

        foreach ($cards as $c) {
            if (($c['name'] ?? '') === $name) {
                $this->info("Control card found: {$c['id']}");
                return self::SUCCESS;
            }
        }

        $this->error("No card named '{$name}' found on board.");
        return self::FAILURE;
    }
}
