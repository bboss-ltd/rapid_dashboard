<?php

namespace App\Domains\Sprints\Data;

readonly class BoardData
{
    public function __construct(
        private string $boardId,
        private string $status,
        private array $statusOptions,
        private string $startsAt,
        private string $endsAt,
        private array $lists,
    )
    {}

    public function getBoardId(): string
    {
        return $this->boardId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusOptions(): array
    {
        return $this->statusOptions;
    }

    public function getStartsAt(): string
    {
        return $this->startsAt;
    }

    public function getEndsAt(): string
    {
        return $this->endsAt;
    }

    public function getLists(): array
    {
        return $this->lists;
    }

    public function getDoneListId(): string
    {
        return $this->getListIdByName('Done');
    }

    public function getListIdByName(string $listName): string
    {
        $listId = 'No list found';

        foreach($this->getLists() as $boardList) {
            if ($listName === $boardList['name']) {
                $listId = $boardList['id'];
                break;
            }
        }

        return $listId;
    }


}
