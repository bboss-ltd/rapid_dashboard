<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Models\SprintSnapshot;

class SprintSnapshotController extends Controller
{
    public function index(Sprint $sprint)
    {
        $snapshots = $sprint->snapshots()
            ->orderByDesc('taken_at')
            ->paginate(20);

        return view('sprintSnapshot.index', compact('sprint', 'snapshots'));
    }

    public function show(Sprint $sprint, SprintSnapshot $snapshot)
    {
        abort_unless($snapshot->sprint_id === $sprint->id, 404);

        $cards = $snapshot->cards()
            ->with('card')
            ->orderByDesc('is_done')
            ->paginate(50);

        return view('sprintSnapshot.show', compact('sprint', 'snapshot', 'cards'));
    }
}
