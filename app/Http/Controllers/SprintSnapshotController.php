<?php

namespace App\Http\Controllers;

use App\Domains\Sprints\Repositories\SprintSnapshotRepository;
use App\Models\Sprint;
use App\Models\SprintSnapshot;

class SprintSnapshotController extends Controller
{
    public function index(Sprint $sprint, SprintSnapshotRepository $snapshotsRepo)
    {
        $snapshots = $snapshotsRepo->paginatedBySprint($sprint, 20);

        return view('sprintSnapshot.index', compact('sprint', 'snapshots'));
    }

    public function show(Sprint $sprint, SprintSnapshot $snapshot, SprintSnapshotRepository $snapshotsRepo)
    {
        abort_unless($snapshot->sprint_id === $sprint->id, 404);

        $cards = $snapshotsRepo->paginatedCards($snapshot, 50);

        return view('sprintSnapshot.show', compact('sprint', 'snapshot', 'cards'));
    }
}
