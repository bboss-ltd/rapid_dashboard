<?php

namespace App\Console\Commands;

use App\Services\Trello\TrelloBoardService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class TrelloPoc extends Command
{
    protected $signature = 'trello:poc {boardId} {estimationFieldId}';
    protected $description = 'POC: List cards and resolve Estimation dropdown text';

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function handle(TrelloBoardService $svc): int
    {
        $boardId = $this->argument('boardId');
        $estimationFieldId = $this->argument('estimationFieldId');

        $optionLookup = $svc->dropdownOptionLookup($boardId);
        $cards = $svc->cardsWithCustomFieldItems($boardId);

        foreach ($cards as $card) {
            $text = $svc->resolveDropdownValueText($card, $estimationFieldId, $optionLookup);
            $this->line(sprintf(
                "%s | %s",
                $card['name'] ?? '(no name)',
                $text ?? '(no estimation)'
            ));
        }

        return self::SUCCESS;
    }
}
