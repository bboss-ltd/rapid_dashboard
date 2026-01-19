<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Services\Trello\TrelloClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

final class SyncSprintRegistryAction
{
    /**
     * Cache of board custom field option text, keyed as:
     * [customFieldId => [optionId => optionText]]
     *
     * @var array<string, array<string, string>>
     */
    private array $optionTextByField = [];

    public function __construct(
        private readonly TrelloClient $trello,
    ) {}

    /**
     * Pull sprint “control cards” from a Trello *registry board* and upsert local sprints.
     *
     * Expected config (in config/trello_sync.php):
     * - trello_sync.registry_board_id
     * - trello_sync.sprint_control.control_field_ids.status
     * - trello_sync.sprint_control.control_field_ids.starts_at
     * - trello_sync.sprint_control.control_field_ids.ends_at
     * - trello_sync.sprint_control.control_field_ids.sprint_board
     * - trello_sync.sprint_control.control_field_ids.done_list_ids (optional)
     *
     * Optional:
     * - trello_sync.sprint_control.status_option_map (optionId => 'planned'|'active'|'closed')
     */
    public function handle(): int
    {
        $registryBoardId = (string) config('trello_sync.registry_board_id');
        if ($registryBoardId === '') {
            throw new RuntimeException('Missing trello_sync.registry_board_id config.');
        }

        /** @var array<string, string> $cfIds */
        $cfIds = config('trello_sync.sprint_control.control_field_ids', []);
        /** @var array<string, string> $cfNames */
        $cfNames = config('trello_sync.sprint_control.control_field_names', []);

        $registryFields = $this->trello->get("/boards/{$registryBoardId}/customFields", []);
        $fieldIdByName = [];
        foreach (($registryFields ?? []) as $field) {
            $name = $field['name'] ?? null;
            $id = $field['id'] ?? null;
            if (is_string($name) && $name !== '' && is_string($id) && $id !== '') {
                $fieldIdByName[Str::lower(trim($name))] = $id;
            }
        }

        $cf = [];
        foreach (['status', 'starts_at', 'ends_at', 'sprint_board', 'done_list_ids'] as $key) {
            $id = $cfIds[$key] ?? '';
            if ($id !== '') {
                $cf[$key] = $id;
                continue;
            }

            $name = $cfNames[$key] ?? '';
            if ($name !== '') {
                $cf[$key] = $fieldIdByName[Str::lower(trim($name))] ?? '';
            }
        }

        foreach (['status', 'starts_at', 'ends_at', 'sprint_board'] as $required) {
            if (empty($cf[$required])) {
                throw new RuntimeException("Missing registry custom field for {$required}. Set trello_sync.sprint_control.control_field_names.{$required} or trello_sync.sprint_control.control_field_ids.{$required}.");
            }
        }

        /** @var array<string, string> $statusOptionMap */
        $statusOptionMap = config('trello_sync.sprint_control.status_option_map', []);

        // Trello supports: /boards/{id}/cards?customFieldItems=true
        $cards = $this->trello->get("/boards/{$registryBoardId}/cards", [
            'fields' => 'name,url,dateLastActivity',
            'customFieldItems' => 'true',
        ]);

        $count = 0;

        foreach (($cards ?? []) as $card) {
            $controlCardId = (string) ($card['id'] ?? '');
            $items = $card['customFieldItems'] ?? [];
            $byFieldId = [];
            foreach ($items as $it) {
                if (!empty($it['idCustomField'])) {
                    $byFieldId[$it['idCustomField']] = $it;
                }
            }

            $status = $this->readDropdownStatus(
                $registryBoardId,
                $cf['status'],
                $byFieldId[$cf['status']] ?? null,
                $statusOptionMap
            );

            $startsAt = $this->readDate($byFieldId[$cf['starts_at']] ?? null);
            $endsAt = $this->readDate($byFieldId[$cf['ends_at']] ?? null);

            // sprint board can be stored as:
            // - board id (24 hex)
            // - shortLink (8 chars)
            // - URL like https://trello.com/b/{shortLink}/{name}
            $boardRef = $this->readTextOrUrl($byFieldId[$cf['sprint_board']] ?? null);
            $sprintBoardIdOrShortLink = $this->extractBoardIdentifier($boardRef);

            if (!$sprintBoardIdOrShortLink) {
                // Not a sprint control card (or incomplete) -> ignore.
                continue;
            }

            // If start/end are missing, still allow creation but make it obvious in UI.
            $startsAt = $startsAt ?? now();
            $endsAt = $endsAt ?? now()->addWeeks(2);

            $doneListIds = [];
            if (!empty($cf['done_list_ids'])) {
                $raw = $this->readTextOrUrl($byFieldId[$cf['done_list_ids']] ?? null);
                $doneListIds = $this->parseDoneListIds($raw);
            }

            $existing = null;
            if ($controlCardId !== '') {
                $existing = Sprint::query()->where('trello_registry_card_id', $controlCardId)->first();
            }
            if (!$existing && $sprintBoardIdOrShortLink) {
                $existing = Sprint::query()->where('trello_board_id', $sprintBoardIdOrShortLink)->first();
            }

            $closedAt = $existing?->closed_at;
            if ($status === 'closed' && !$closedAt) {
                $closedAt = $endsAt ?? now();
            }

            $data = [
                'name' => (string) ($card['name'] ?? ('Sprint ' . Str::upper(Str::random(4)))),
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'closed_at' => $closedAt,
                'trello_board_id' => $sprintBoardIdOrShortLink,
                'done_list_ids' => $doneListIds ?: null,
                'trello_registry_card_id' => $controlCardId,
            ];

            if ($existing) {
                $existing->fill($data);
                $existing->save();
            } else {
                Sprint::create($data);
            }

            $count++;
        }

        return $count;
    }

    private function readDropdownStatus(
        string $registryBoardId,
        string $statusFieldId,
        ?array $item,
        array $statusOptionMap
    ): ?string {
        if (!$item) return null;

        $optionId = $item['idValue'] ?? null;
        if (!$optionId) return null;

        // Preferred: explicit mapping in config (stable even if option text changes)
        if (isset($statusOptionMap[$optionId])) {
            return $statusOptionMap[$optionId];
        }

        // Fallback: resolve option text from board custom fields and normalise
        $text = $this->resolveOptionText($registryBoardId, $statusFieldId, $optionId);
        if ($text === null) return null;

        $norm = Str::of($text)->lower()->trim();

        if ($norm->contains('close')) return 'closed';
        if ($norm->contains('active') || $norm->contains('current') || $norm->contains('open')) return 'active';
        if ($norm->contains('plan') || $norm->contains('next') || $norm->contains('upcoming')) return 'planned';

        return null;
    }

    private function resolveOptionText(string $boardId, string $customFieldId, string $optionId): ?string
    {
        if (!isset($this->optionTextByField[$customFieldId])) {
            // Build option maps for this board once
            $fields = $this->trello->get("/boards/{$boardId}/customFields", []);
            $map = [];

            foreach (($fields ?? []) as $f) {
                $id = $f['id'] ?? null;
                if (!$id) continue;

                $options = $f['options'] ?? [];
                $optMap = [];
                foreach ($options as $opt) {
                    $oid = $opt['id'] ?? null;
                    $oval = $opt['value']['text'] ?? null;
                    if ($oid && is_string($oval)) {
                        $optMap[$oid] = $oval;
                    }
                }
                $map[$id] = $optMap;
            }

            $this->optionTextByField = $map + $this->optionTextByField;
        }

        return $this->optionTextByField[$customFieldId][$optionId] ?? null;
    }

    private function readDate(?array $item): ?Carbon
    {
        if (!$item) return null;

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

        if (!empty($item['value']['text'])) return (string) $item['value']['text'];
        if (isset($item['value']['number'])) return (string) $item['value']['number'];
        if (isset($item['value']['checked'])) return $item['value']['checked'] ? 'true' : 'false';

        return null;
    }

    private function extractBoardIdentifier(?string $ref): ?string
    {
        if (!$ref) return null;

        $t = trim($ref);

        // Board id (24 hex)
        if (preg_match('/^[a-f0-9]{24}$/i', $t)) {
            return $t;
        }

        // Board shortLink (8 chars) is accepted by Trello as a board identifier
        if (preg_match('/^[a-zA-Z0-9]{8}$/', $t)) {
            return $t;
        }

        // URL like https://trello.com/b/{shortLink}/{name}
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
