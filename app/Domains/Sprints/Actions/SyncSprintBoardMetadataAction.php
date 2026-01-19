<?php

namespace App\Domains\Sprints\Actions;

use App\Models\Sprint;
use App\Services\Trello\TrelloClient;
use App\Services\Trello\TrelloSprintBoardReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class SyncSprintBoardMetadataAction
{
    public function __construct(
        private readonly TrelloClient $trello,
        private readonly TrelloSprintBoardReader $reader,
    ) {}

    public function run(Sprint $sprint): void
    {
        $boardId = $sprint->trello_board_id;

        $lists = $this->trello->get("/boards/{$boardId}/lists", [
            'fields' => 'name',
        ]);

        $doneListNames = (array) config('trello_sync.sprint_board.done_list_names', ['Done']);
        $remakesListName = (string) config('trello_sync.sprint_board.remakes_list_name', 'Remakes');
        $adminListName = (string) config('trello_sync.sprint_board.sprint_admin_list_name', 'Sprint Admin');
        $controlCardName = config('trello_sync.sprint_board.control_card_name');
        $startsAtFieldName = (string) config('trello_sync.sprint_board.starts_at_field_name', 'Starts At');
        $endsAtFieldName = (string) config('trello_sync.sprint_board.ends_at_field_name', 'Ends At');
        $statusFieldName = (string) config('trello_sync.sprint_board.status_field_name', 'Sprint Status');
        $closedStatusLabel = (string) config('trello_sync.sprint_board.closed_status_label', 'Closed');
        $divergedLabelName = (string) config('trello_sync.sprint_board.diverged_label_name', 'Diverged Dates');
        $divergedLabelColor = (string) config('trello_sync.sprint_board.diverged_label_color', 'red');

        $doneListIds = $this->findListIdsByNames($lists, $doneListNames);
        $remakesListId = $this->findListIdByName($lists, $remakesListName);
        $adminListId = $this->findListIdByName($lists, $adminListName);

        $controlCard = null;
        if ($adminListId) {
            $cards = $this->trello->get("/lists/{$adminListId}/cards", [
                'fields' => 'name,desc,idLabels',
                'customFieldItems' => 'true',
            ]);

            if (is_string($controlCardName) && $controlCardName !== '') {
                foreach ($cards as $card) {
                    if (($card['name'] ?? '') === $controlCardName) {
                        $controlCard = $card;
                        break;
                    }
                }
            }

            if (!$controlCard && !empty($cards)) {
                $controlCard = $cards[0];
            }

            if ($controlCard && !empty($controlCard['id'])) {
                $controlCard = $this->trello->get("/cards/{$controlCard['id']}", [
                    'fields' => 'name,desc,idLabels',
                    'customFieldItems' => 'true',
                ]);
            }
        }

        $customFields = $this->reader->fetchCustomFields($boardId);
        $statusFieldId = $this->reader->findCustomFieldIdByName($customFields, $statusFieldName);
        $startsAtFieldId = $this->reader->findCustomFieldIdByName($customFields, $startsAtFieldName);
        $endsAtFieldId = $this->reader->findCustomFieldIdByName($customFields, $endsAtFieldName);
        $lookup = $this->reader->buildDropdownLookup($customFields);

        $closedOptionId = null;
        if ($statusFieldId && isset($lookup[$statusFieldId])) {
            foreach ($lookup[$statusFieldId] as $optionId => $label) {
                if (Str::of($label)->lower()->contains(Str::lower($closedStatusLabel))) {
                    $closedOptionId = $optionId;
                    break;
                }
            }
        }

        if (empty($sprint->done_list_ids) && !empty($doneListIds)) {
            $sprint->done_list_ids = $doneListIds;
        }

        if ($remakesListId) {
            $sprint->remakes_list_id = $remakesListId;
        }

        if ($controlCard) {
            $sprint->trello_control_card_id = (string) ($controlCard['id'] ?? $sprint->trello_control_card_id);
            $sprint->sprint_goal = trim((string) ($controlCard['desc'] ?? '')) ?: $sprint->sprint_goal;
        }

        if ($statusFieldId) {
            $sprint->trello_status_custom_field_id = $statusFieldId;
        }

        if ($closedOptionId) {
            $sprint->trello_closed_option_id = $closedOptionId;
        }

        $diverged = $controlCard
            ? $this->hasDivergedControlCard($sprint, $controlCard, $startsAtFieldId, $endsAtFieldId, $statusFieldId, $lookup)
            : false;

        if ($controlCard && $diverged) {
            $labelId = $this->ensureLabel($boardId, $divergedLabelName, $divergedLabelColor);
            if ($labelId) {
                $this->applyLabelToCard($controlCard, $labelId);
            }

            $registryBoardId = (string) config('trello_sync.registry_board_id');
            if ($registryBoardId !== '' && $sprint->trello_registry_card_id) {
                $registryLabelId = $this->ensureLabel($registryBoardId, $divergedLabelName, $divergedLabelColor);
                if ($registryLabelId) {
                    $this->applyLabelToCardId((string) $sprint->trello_registry_card_id, $registryLabelId);
                }
            }
        }

        if ($controlCard && !$diverged) {
            $labelId = $this->findLabelIdByName($boardId, $divergedLabelName);
            if ($labelId) {
                $this->removeLabelFromCard($controlCard, $labelId);
            }

            $registryBoardId = (string) config('trello_sync.registry_board_id');
            if ($registryBoardId !== '' && $sprint->trello_registry_card_id) {
                $registryLabelId = $this->findLabelIdByName($registryBoardId, $divergedLabelName);
                if ($registryLabelId) {
                    $this->removeLabelFromCardId((string) $sprint->trello_registry_card_id, $registryLabelId);
                }
            }
        }

        if ($sprint->isDirty()) {
            $sprint->save();
        }
    }

    /**
     * @param array<int, array{id?: string, name?: string}> $lists
     * @param array<int, string> $names
     * @return array<int, string>
     */
    private function findListIdsByNames(array $lists, array $names): array
    {
        $targets = collect($names)
            ->map(fn($name) => Str::lower(trim((string) $name)))
            ->filter()
            ->values();

        if ($targets->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($lists as $list) {
            $name = Str::lower(trim((string) ($list['name'] ?? '')));
            if ($name !== '' && $targets->contains($name)) {
                $id = $list['id'] ?? null;
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array{id?: string, name?: string}> $lists
     */
    private function findListIdByName(array $lists, string $name): ?string
    {
        $target = Str::lower(trim($name));
        if ($target === '') {
            return null;
        }

        foreach ($lists as $list) {
            if (Str::lower(trim((string) ($list['name'] ?? ''))) === $target) {
                return $list['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param array{id?: string, customFieldItems?: array, idLabels?: array} $card
     */
    private function hasDivergedControlCard(
        Sprint $sprint,
        array $card,
        ?string $startsAtFieldId,
        ?string $endsAtFieldId,
        ?string $statusFieldId,
        array $dropdownLookup,
    ): bool {
        $diverged = false;

        if ($startsAtFieldId) {
            $controlStart = $this->readDateFromCard($card, $startsAtFieldId);
            $sprintStart = $sprint->starts_at;
            if (!$controlStart || !$sprintStart || $controlStart->toDateString() !== $sprintStart->toDateString()) {
                $diverged = true;
            }
        }

        if ($endsAtFieldId) {
            $controlEnd = $this->readDateFromCard($card, $endsAtFieldId);
            $sprintEnd = $sprint->ends_at;
            if (!$controlEnd || !$sprintEnd || $controlEnd->toDateString() !== $sprintEnd->toDateString()) {
                $diverged = true;
            }
        }

        if ($statusFieldId) {
            $controlStatus = $this->reader->resolveDropdownText($card, $statusFieldId, $dropdownLookup);
            $controlStatus = $controlStatus ? Str::lower(trim($controlStatus)) : null;
            $registryStatus = $sprint->status ? Str::lower(trim((string) $sprint->status)) : null;
            if (!$controlStatus || !$registryStatus || $controlStatus !== $registryStatus) {
                $diverged = true;
            }
        }

        return $diverged;
    }

    /**
     * @param array{customFieldItems?: array} $card
     */
    private function readDateFromCard(array $card, string $fieldId): ?Carbon
    {
        foreach (($card['customFieldItems'] ?? []) as $item) {
            if (($item['idCustomField'] ?? null) !== $fieldId) {
                continue;
            }

            $val = $item['value']['date'] ?? null;
            if (!$val) {
                return null;
            }

            try {
                return Carbon::parse($val);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function ensureLabel(string $boardId, string $labelName, string $labelColor): ?string
    {
        $labelName = trim($labelName);
        if ($labelName === '') {
            return null;
        }

        $existingId = $this->findLabelIdByName($boardId, $labelName);
        if ($existingId) {
            return $existingId;
        }

        $created = $this->trello->post('/labels', [
            'idBoard' => $boardId,
            'name' => $labelName,
            'color' => $labelColor !== '' ? $labelColor : 'red',
        ]);

        return $created['id'] ?? null;
    }

    private function findLabelIdByName(string $boardId, string $labelName): ?string
    {
        $labelName = trim($labelName);
        if ($labelName === '') {
            return null;
        }

        $labels = $this->trello->get("/boards/{$boardId}/labels", [
            'fields' => 'name,color',
        ]);

        foreach ($labels as $label) {
            if (Str::lower(trim((string) ($label['name'] ?? ''))) === Str::lower($labelName)) {
                return $label['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param array{id?: string, idLabels?: array} $card
     */
    private function applyLabelToCard(array $card, string $labelId): void
    {
        $cardId = $card['id'] ?? null;
        if (!$cardId) {
            return;
        }

        $existing = $card['idLabels'] ?? [];
        if (is_array($existing) && in_array($labelId, $existing, true)) {
            return;
        }

        $this->trello->post("/cards/{$cardId}/idLabels", [], [
            'value' => $labelId,
        ]);
    }

    private function applyLabelToCardId(string $cardId, string $labelId): void
    {
        $card = $this->trello->get("/cards/{$cardId}", [
            'fields' => 'idLabels',
        ]);

        $existing = $card['idLabels'] ?? [];
        if (is_array($existing) && in_array($labelId, $existing, true)) {
            return;
        }

        $this->trello->post("/cards/{$cardId}/idLabels", [], [
            'value' => $labelId,
        ]);
    }

    /**
     * @param array{id?: string, idLabels?: array} $card
     */
    private function removeLabelFromCard(array $card, string $labelId): void
    {
        $cardId = $card['id'] ?? null;
        if (!$cardId) {
            return;
        }

        $existing = $card['idLabels'] ?? [];
        if (!is_array($existing) || !in_array($labelId, $existing, true)) {
            return;
        }

        $this->trello->delete("/cards/{$cardId}/idLabels/{$labelId}");
    }

    private function removeLabelFromCardId(string $cardId, string $labelId): void
    {
        $card = $this->trello->get("/cards/{$cardId}", [
            'fields' => 'idLabels',
        ]);

        $existing = $card['idLabels'] ?? [];
        if (!is_array($existing) || !in_array($labelId, $existing, true)) {
            return;
        }

        $this->trello->delete("/cards/{$cardId}/idLabels/{$labelId}");
    }
}
