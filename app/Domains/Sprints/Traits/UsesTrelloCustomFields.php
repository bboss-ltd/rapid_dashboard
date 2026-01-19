<?php

namespace App\Domains\Sprints\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait UsesTrelloCustomFields
{
    protected array $optionTextByField = [];

    protected function readDropdownStatus(
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

    protected function resolveOptionText(string $boardId, string $customFieldId, string $optionId): ?string
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

    protected function readDate(?array $item): ?Carbon
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

    protected function readTextOrUrl(?array $item): ?string
    {
        if (!$item) return null;

        if (!empty($item['value']['text'])) return (string) $item['value']['text'];
        if (isset($item['value']['number'])) return (string) $item['value']['number'];
        if (isset($item['value']['checked'])) return $item['value']['checked'] ? 'true' : 'false';

        return null;
    }
}
