<?php

namespace App\Services\Trello;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class TrelloBoardService
{
    public function __construct(private TrelloClient $trello) {}

    public function customFields(string $boardId): array
    {
        return $this->trello->get("/boards/{$boardId}/customFields");
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function cardsWithCustomFieldItems(string $boardId): array
    {
        return $this->trello->get("/boards/{$boardId}/cards", [
            'fields' => 'name,idList,idMembers,labels,due,dateLastActivity',
            'customFieldItems' => 'true',
        ]);
    }

    /**
     * Build a lookup: customFieldId => [ optionId => optionText ]
     * (only meaningful for list/dropdown fields)
     */
    public function dropdownOptionLookup(string $boardId): array
    {
        $fields = $this->customFields($boardId);

        $lookup = [];
        foreach ($fields as $field) {
            if (($field['type'] ?? null) !== 'list') continue;

            $options = $field['options'] ?? [];
            foreach ($options as $opt) {
                $optId = $opt['id'] ?? null;
                $text = Arr::get($opt, 'value.text');
                if ($optId && $text !== null) {
                    $lookup[$field['id']][$optId] = $text;
                }
            }
        }

        return $lookup;
    }

    /**
     * Get Estimation selection text for a card given the field ID + option map.
     */
    public function resolveDropdownValueText(array $card, string $customFieldId, array $optionLookup): ?string
    {
        $items = $card['customFieldItems'] ?? [];
        foreach ($items as $item) {
            if (($item['idCustomField'] ?? null) !== $customFieldId) continue;

            $idValue = $item['idValue'] ?? null; // dropdown option id
            if (!$idValue) return null;

            return $optionLookup[$customFieldId][$idValue] ?? null;
        }

        return null;
    }

    /**
     * Write a numeric value to a number custom field on a card.
     */
    public function setNumberCustomField(string $cardId, string $customFieldId, int $value): array
    {
        return $this->trello->put("/cards/{$cardId}/customField/{$customFieldId}/item", [
            'value' => ['number' => (string) $value],
        ]);
    }
}
