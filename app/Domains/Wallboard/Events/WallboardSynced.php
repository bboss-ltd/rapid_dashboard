<?php

namespace App\Domains\Wallboard\Events;

use App\Models\Sprint;
use App\Models\SprintSnapshot;

final class WallboardSynced
{
    public function __construct(
        public Sprint $sprint,
        public SprintSnapshot $snapshot,
        public ?SprintSnapshot $reconcileSnapshot,
    ) {}
}
