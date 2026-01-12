<?php

namespace App\Domains\Sprints\Policies;

use App\Models\Sprint;

final class ShouldReconcileSprintPolicy
{
    public function check(Sprint $sprint): bool
    {
        if ($sprint->isClosed()) return false;

        $latest = $sprint->snapshots()
            ->where('type', 'ad_hoc')
            ->latest('taken_at')
            ->first();

        $minMinutes = (int) config('reconciliation.min_minutes_between_reconcile_snapshots', 30);

        if (!$latest) return true;

        // If we are near the end of the sprint, reconcile more aggressively.
        $forceWithinHours = (int) config('reconciliation.force_reconcile_within_hours_of_end', 8);
        if ($sprint->ends_at && now()->diffInHours($sprint->ends_at, false) <= $forceWithinHours) {
            return true;
        }

        return $latest->taken_at->diffInMinutes(now()) >= $minMinutes;
    }
}
