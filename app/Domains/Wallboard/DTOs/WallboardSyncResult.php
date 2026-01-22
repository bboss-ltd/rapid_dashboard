<?php

namespace App\Domains\Wallboard\DTOs;

use App\Models\SprintSnapshot;

final class WallboardSyncResult
{
    public function __construct(
        public SprintSnapshot $snapshot,
        public ?SprintSnapshot $reconcileSnapshot,
    ) {}
}
