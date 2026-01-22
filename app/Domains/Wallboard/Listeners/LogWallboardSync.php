<?php

namespace App\Domains\Wallboard\Listeners;

use App\Domains\Wallboard\Events\WallboardSynced;
use Illuminate\Support\Facades\Log;

final class LogWallboardSync
{
    public function handle(WallboardSynced $event): void
    {
        Log::info('wallboard.synced', [
            'sprint_id' => $event->sprint->id,
            'snapshot_id' => $event->snapshot->id,
            'reconcile_snapshot_id' => $event->reconcileSnapshot?->id,
        ]);
    }
}
