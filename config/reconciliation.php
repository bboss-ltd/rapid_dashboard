<?php

return [
    // Only create a reconcile snapshot if the last ad_hoc snapshot is older than this.
    'min_minutes_between_reconcile_snapshots' => 30,

    // Always reconcile in the last N hours before sprint end (helps close accuracy).
    'force_reconcile_within_hours_of_end' => 8,
];
