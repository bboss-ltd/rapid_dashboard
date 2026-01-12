<?php

namespace App\Domains\WorkTracking\Ports;

interface BoardProvider {
    public function fetchBoard(string $boardId): BoardDTO;
    public function fetchCards(string $boardId): array; // CardDTO[]
}

