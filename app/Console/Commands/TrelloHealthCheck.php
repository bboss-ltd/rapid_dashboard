<?php

namespace App\Console\Commands;

use App\Services\Trello\TrelloClient;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class TrelloHealthCheck extends Command
{
    protected $signature = 'trello:health-check {--sample=3 : Number of sprint boards to sample}';
    protected $description = 'Validate Trello connectivity and required registry/sprint board configuration';

    public function handle(TrelloClient $trello, TrelloSprintBoardReader $reader): int
    {
        $ok = true;

        $registryBoardId = (string) config('trello_sync.registry_board_id');
        if ($registryBoardId === '') {
            $this->fail('Missing trello_sync.registry_board_id (TRELLO_REGISTRY_BOARD_ID).');
            return self::FAILURE;
        }

        try {
            $board = $trello->get("/boards/{$registryBoardId}", ['fields' => 'name']);
            $this->outPass("Check: Trello connectivity -> OK");
            $this->outPass("Check: Registry board reachable -> OK ({$registryBoardId})");
            $this->outInfo("Registry board name: " . ($board['name'] ?? $registryBoardId));
        } catch (RequestException $e) {
            $this->outFail("Check: Trello connectivity -> FAIL");
            $this->outFail("Check: Registry board reachable -> FAIL ({$registryBoardId})");
            $this->outFail($e->getMessage());
            return self::FAILURE;
        }

        $registryFields = $trello->get("/boards/{$registryBoardId}/customFields", []);
        $registryFieldIds = collect($registryFields)->pluck('id')->filter()->all();
        $registryFieldNames = collect($registryFields)->pluck('name')->filter()->map(fn($n) => strtolower(trim((string) $n)))->all();

        $requiredFieldIds = (array) config('trello_sync.sprint_control.control_field_ids', []);
        $requiredFieldNames = (array) config('trello_sync.sprint_control.control_field_names', []);

        foreach (['status', 'starts_at', 'ends_at', 'sprint_board'] as $key) {
            $id = $requiredFieldIds[$key] ?? null;
            $name = $requiredFieldNames[$key] ?? null;

            if ($id) {
                if (!in_array($id, $registryFieldIds, true)) {
                    $this->outFail("Check: Registry custom field id -> FAIL ({$key} = {$id})");
                    $ok = false;
                } else {
                    $this->outPass("Check: Registry custom field id -> OK ({$key} = {$id})");
                }
                continue;
            }

            if ($name) {
                if (!in_array(strtolower(trim((string) $name)), $registryFieldNames, true)) {
                    $this->outFail("Check: Registry custom field name -> FAIL ({$key} = {$name})");
                    $ok = false;
                } else {
                    $this->outPass("Check: Registry custom field name -> OK ({$key} = {$name})");
                }
                continue;
            }

            $this->outFail("Check: Registry custom field mapping -> FAIL ({$key})");
            $ok = false;
        }

        $cards = $trello->get("/boards/{$registryBoardId}/cards", [
            'fields' => 'name',
            'customFieldItems' => 'true',
        ]);

        $this->outInfo('Registry cards found: ' . count($cards));
        if (count($cards) === 0) {
            $this->warnOut('Check: Registry cards -> WARN (0 found)');
        } else {
            $this->outPass('Check: Registry cards -> OK');
        }

        $sample = max(0, (int) $this->option('sample'));
        $sprintBoardFieldId = $requiredFieldIds['sprint_board'] ?? null;
        if (!$sprintBoardFieldId && ($requiredFieldNames['sprint_board'] ?? null)) {
            $name = strtolower(trim((string) $requiredFieldNames['sprint_board']));
            $sprintBoardFieldId = collect($registryFields)->first(function ($field) use ($name) {
                return strtolower(trim((string) ($field['name'] ?? ''))) === $name;
            })['id'] ?? null;
        }

        $doneListNames = (array) config('trello_sync.sprint_board.done_list_names', ['Done']);
        $remakesListName = (string) config('trello_sync.sprint_board.remakes_list_name', 'Remakes');
        $adminListName = (string) config('trello_sync.sprint_board.sprint_admin_list_name', 'Sprint Admin');
        $controlCardName = (string) config('trello_sync.sprint_board.control_card_name', 'Sprint Control');
        $startsAtFieldName = (string) config('trello_sync.sprint_board.starts_at_field_name', 'Starts At');
        $endsAtFieldName = (string) config('trello_sync.sprint_board.ends_at_field_name', 'Ends At');
        $statusFieldName = (string) config('trello_sync.sprint_board.status_field_name', 'Sprint Status');

        $checked = 0;
        foreach ($cards as $card) {
            if ($checked >= $sample) break;
            if (!$sprintBoardFieldId) break;

            $boardRef = $this->readTextOrUrl($card['customFieldItems'] ?? [], $sprintBoardFieldId);
            $boardId = $this->extractBoardIdentifier($boardRef);
            if (!$boardId) {
                continue;
            }

            $checked++;
            $this->outInfo("\nSprint board sample #{$checked}: {$boardId}");

            try {
                $sprintBoard = $trello->get("/boards/{$boardId}", ['fields' => 'name']);
                $this->outPass('Check: Sprint board reachable -> OK (' . ($sprintBoard['name'] ?? $boardId) . ')');
            } catch (RequestException $e) {
                $this->outFail("Check: Sprint board reachable -> FAIL ({$boardId})");
                $ok = false;
                continue;
            }

            $lists = $trello->get("/boards/{$boardId}/lists", ['fields' => 'name']);
            $listNames = collect($lists)->pluck('name')->map(fn($n) => strtolower(trim((string) $n)))->all();

            $missingDone = array_filter($doneListNames, function ($name) use ($listNames) {
                return !in_array(strtolower(trim((string) $name)), $listNames, true);
            });
            if (!empty($missingDone)) {
                $this->warnOut('Check: Done list(s) -> WARN (missing: ' . implode(', ', $missingDone) . ')');
            } else {
                $this->outPass('Check: Done list(s) -> OK');
            }

            if (!in_array(strtolower(trim($remakesListName)), $listNames, true)) {
                $this->warnOut("Check: Remakes list -> WARN (missing: {$remakesListName})");
            } else {
                $this->outPass('Check: Remakes list -> OK');
            }

            if (!in_array(strtolower(trim($adminListName)), $listNames, true)) {
                $this->warnOut("Check: Sprint Admin list -> WARN (missing: {$adminListName})");
            } else {
                $this->outPass('Check: Sprint Admin list -> OK');
            }

            $customFields = $reader->fetchCustomFields($boardId);
            $missingFields = [];
            foreach ([$startsAtFieldName, $endsAtFieldName, $statusFieldName] as $requiredName) {
                if (!$reader->findCustomFieldIdByName($customFields, $requiredName)) {
                    $missingFields[] = $requiredName;
                }
            }
            if (!empty($missingFields)) {
                $this->warnOut('Check: Sprint board custom fields -> WARN (missing: ' . implode(', ', $missingFields) . ')');
            } else {
                $this->outPass('Check: Sprint board custom fields -> OK');
            }

            $adminList = collect($lists)->first(function ($list) use ($adminListName) {
                return strtolower(trim((string) ($list['name'] ?? ''))) === strtolower(trim($adminListName));
            });

            if ($adminList && !empty($adminList['id'])) {
                $adminCards = $trello->get("/lists/{$adminList['id']}/cards", ['fields' => 'name']);
                $hasControl = collect($adminCards)->contains(function ($c) use ($controlCardName) {
                    return strtolower(trim((string) ($c['name'] ?? ''))) === strtolower(trim($controlCardName));
                });

                if (!$hasControl) {
                    $this->warnOut("Check: Control card -> WARN (missing '{$controlCardName}' in {$adminListName})");
                } else {
                    $this->outPass('Check: Control card -> OK');
                }
            }
        }

        if ($sample > 0 && $checked === 0) {
            $this->warnOut('Check: Sprint board samples -> WARN (none found from registry cards)');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function readTextOrUrl(array $items, string $fieldId): ?string
    {
        foreach ($items as $item) {
            if (($item['idCustomField'] ?? null) !== $fieldId) continue;
            if (!empty($item['value']['text'])) return (string) $item['value']['text'];
            if (isset($item['value']['number'])) return (string) $item['value']['number'];
            if (isset($item['value']['checked'])) return $item['value']['checked'] ? 'true' : 'false';
        }

        return null;
    }

    private function extractBoardIdentifier(?string $ref): ?string
    {
        if (!$ref) return null;

        $t = trim($ref);

        if (preg_match('/^[a-f0-9]{24}$/i', $t)) {
            return $t;
        }

        if (preg_match('/^[a-zA-Z0-9]{8}$/', $t)) {
            return $t;
        }

        if (preg_match('~/b/([a-zA-Z0-9]{8})/~', $t, $m)) {
            return $m[1];
        }

        return null;
    }

    private function outPass(string $message): void
    {
        $this->line("<fg=green>{$message}</>");
    }

    private function outFail(string $message): void
    {
        $this->line("<fg=red>{$message}</>");
    }

    private function warnOut(string $message): void
    {
        $this->line("<fg=yellow>{$message}</>");
    }

    private function outInfo(string $message): void
    {
        $this->line("<fg=white>{$message}</>");
    }
}
