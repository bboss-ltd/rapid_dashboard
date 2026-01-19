<?php

namespace App\Services\Trello;

class TrelloSprintBoardReader
{
    public function __construct(private TrelloClient $trello) {}

    public function fetchCustomFields(string $boardId): array
    {
        return $this->trello->get("/boards/{$boardId}/customFields");
    }

    public function fetchCards(string $boardId): array
    {
        return $this->trello->get("/boards/{$boardId}/cards", [
            'fields' => 'name,idList,dateLastActivity',
            'customFieldItems' => 'true',
        ]);
    }

    public function buildDropdownLookup(array $customFields): array
    {
        // [customFieldId => [optionId => optionText]]
        $lookup = [];

        foreach ($customFields as $field) {
            if (($field['type'] ?? null) !== 'list') continue;
            $fieldId = $field['id'] ?? null;
            if (!$fieldId) continue;

            foreach (($field['options'] ?? []) as $opt) {
                $optId = $opt['id'] ?? null;
                $text = $opt['value']['text'] ?? null;
                if ($optId && $text !== null) {
                    $lookup[$fieldId][$optId] = $text;
                }
            }
        }

        return $lookup;
    }

    public function findCustomFieldIdByName(array $customFields, string $name): ?string
    {
        $target = strtolower(trim($name));
        if ($target === '') return null;

        foreach ($customFields as $field) {
            $fieldName = strtolower(trim((string) ($field['name'] ?? '')));
            if ($fieldName === $target) return $field['id'] ?? null;
        }
        return null;
    }

    public function resolveDropdownText(array $card, string $customFieldId, array $lookup): ?string
    {
        foreach (($card['customFieldItems'] ?? []) as $item) {
            if (($item['idCustomField'] ?? null) !== $customFieldId) continue;
            $idValue = $item['idValue'] ?? null;
            return $idValue ? ($lookup[$customFieldId][$idValue] ?? null) : null;
        }
        return null;
    }
}
