<?php

namespace App\Console\Commands;

use App\Services\Trello\TrelloClient;
use Illuminate\Console\Command;

class TrelloInspectBoard extends Command
{
    protected $signature = 'trello:inspect-board {boardId}';
    protected $description = 'Print useful info for a Trello board (lists, custom fields, etc.)';

    public function handle(TrelloClient $trello): int
    {
        $boardId = (string) $this->argument('boardId');

        $board = $trello->get("/boards/{$boardId}", [
            'fields' => 'name,closed',
        ]);

        $this->line("Board: <info>{$board['name']}</info>");
        $this->line("Board ID: {$boardId}");
        $this->line("Closed: " . (($board['closed'] ?? false) ? 'true' : 'false'));
        $this->newLine();

        $this->line('<comment>Lists (use IDs for done_list_ids):</comment>');
        $lists = $trello->get("/boards/{$boardId}/lists", [
            'fields' => 'name,closed',
        ]);

        foreach ($lists as $l) {
            $closed = ($l['closed'] ?? false) ? ' (closed)' : '';
            $this->line("- {$l['name']}  |  {$l['id']}{$closed}");
        }

        $this->newLine();

        $this->line('<comment>Custom Fields (look for “Estimation”, and later your Sprint Status field):</comment>');
        $fields = $trello->get("/boards/{$boardId}/customFields");

        foreach ($fields as $f) {
            $this->line("- {$f['name']}  |  {$f['id']}  |  type={$f['type']}");
            if (($f['type'] ?? null) === 'list') {
                foreach (($f['options'] ?? []) as $opt) {
                    $label = $opt['value']['text'] ?? '';
                    $this->line("    - option: {$label}  |  {$opt['id']}");
                }
            }
        }

        return self::SUCCESS;
    }
}
