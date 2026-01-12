<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Services\Trello\TrelloClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SyncSprintRegistryAction
{
    public function __construct(
        private readonly TrelloClient $trello,
    ) {}

    public function handle(): int
    {
        $boardId = config('trello_sync.registry_board_id');
        if (!$boardId) {
            throw new \RuntimeException('Missing trello.registry_board_id config.');
        }

        $cf = config('trello_sync.sprint_control.control_field_ids', []);
        foreach (['status','starts_at','ends_at','sprint_board'] as $required) {
            if (empty($cf[$required])) {
                throw new \RuntimeException("Missing trello_sync.control_field_ids.$required config.");
            }
        }

        $statusOptionMap = config('trello_sync.sprint_control.status_option_map', []);

        // Get cards + their custom field items in one go
        // Trello supports: /boards/{id}/cards?customFieldItems=true
        $cards = $this->trello->get("/boards/{$boardId}/cards", [
            'fields' => 'name,closed,desc,url,idBoard,dateLastActivity',
            'customFieldItems' => 'true',
        ]);

        $count = 0;

        foreach ($cards as $card) {
            $items = $card['customFieldItems'] ?? [];
            $byFieldId = [];
            foreach ($items as $it) {
                if (!empty($it['idCustomField'])) {
                    $byFieldId[$it['idCustomField']] = $it;
                }
            }

            $status = $this->readDropdown($byFieldId[$cf['status']] ?? null, $statusOptionMap);
            $startsAt = $this->readDate($byFieldId[$cf['starts_at']] ?? null);
            $endsAt = $this->readDate($byFieldId[$cf['ends_at']] ?? null);

            // Sprint board ref can be URL or raw board id depending on your field usage
            $boardRef = $this->readTextOrUrl($byFieldId[$cf['sprint_board']] ?? null);
            $sprintBoardId = $this->extractBoardId($boardRef) ?: $boardRef; // if ref itself is an id
            $sprintBoardId = is_string($sprintBoardId) ? trim($sprintBoardId) : null;

            if (!$sprintBoardId) {
                // not a sprint card (or incomplete); ignore
                continue;
            }

            // Optional done list ids (json/text)
            $doneListIds = [];
            if (!empty($cf['done_list_ids'])) {
                $raw = $this->readTextOrUrl($byFieldId[$cf['done_list_ids']] ?? null);
                $doneListIds = $this->parseDoneListIds($raw);
            }

            // Determine closed_at if status indicates closed
            $closedAt = null;
            if ($status === 'closed') {
                // Use endsAt if present, else now
                $closedAt = $endsAt ?? now();
            }

            // Upsert
            Sprint::updateOrCreate(
                ['trello_control_card_id' => $card['id']],
                [
                    'name' => $card['name'] ?? ('Sprint ' . Str::upper(Str::random(4))),
                    'starts_at' => $startsAt ?? now(),
                    'ends_at' => $endsAt ?? (now()->addWeeks(2)),
                    'closed_at' => $closedAt,
                    'trello_board_id' => $sprintBoardId,
                    'done_list_ids' => $doneListIds ?: null,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function readDropdown(?array $item, array $optionMap): ?string
    {
        if (!$item) return null;

        $idValue = $item['idValue'] ?? null;
        if (!$idValue) return null;

        // prefer optionMap (stable)
        if (isset($optionMap[$idValue])) return $optionMap[$idValue];

        // fallback: if you used labels and not option ids (less ideal)
        return null;
    }

    private function readDate(?array $item): ?Carbon
    {
        if (!$item) return null;

        // date custom field appears under value.date
        $val = $item['value']['date'] ?? null;
        if (!$val) return null;

        try {
            return Carbon::parse($val);
        } catch (\Throwable) {
            return null;
        }
    }

    private function readTextOrUrl(?array $item): ?string
    {
        if (!$item) return null;

        // text custom field: value.text
        if (!empty($item['value']['text'])) return (string) $item['value']['text'];

        // number custom field: value.number
        if (isset($item['value']['number'])) return (string) $item['value']['number'];

        // checkbox: value.checked (true/false)
        if (isset($item['value']['checked'])) return $item['value']['checked'] ? 'true' : 'false';

        return null;
    }

    private function extractBoardId(?string $ref): ?string
    {
        if (!$ref) return null;

        // Trello board URLs are like https://trello.com/b/{shortLink}/{name}
        // That doesn't include board id. If you store shortLink, you can look up /boards/{shortLink}
        // If you store the actual board id, return it as-is.
        // Here: if it looks like an id (24 chars hex), accept it.
        $t = trim($ref);
        if (preg_match('/^[a-f0-9]{24}$/i', $t)) {
            return $t;
        }

        // If you store shortLink (8 chars), you can also accept it:
        if (preg_match('/^[a-zA-Z0-9]{8}$/', $t)) {
            // We'll treat shortLink as a board identifier usable in Trello API
            return $t;
        }

        // If you store URL, try to extract /b/{shortLink}/
        if (preg_match('~/b/([a-zA-Z0-9]{8})/~', $t, $m)) {
            return $m[1];
        }

        return null;
    }

    private function parseDoneListIds(?string $raw): array
    {
        if (!$raw) return [];

        $raw = trim($raw);

        // JSON array?
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn($v) => is_string($v) && $v !== ''));
            }
        }

        // comma-separated
        if (str_contains($raw, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        // single value
        return $raw ? [$raw] : [];
    }
}
