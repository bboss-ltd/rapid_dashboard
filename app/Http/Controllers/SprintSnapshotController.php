<?php

namespace App\Http\Controllers;

use App\Models\SprintSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SprintSnapshotController extends Controller
{
    public function index(Request $request): View
    {
        $sprintSnapshots = SprintSnapshot::all();

        return view('sprintSnapshot.index', [
            'sprintSnapshots' => $sprintSnapshots,
        ]);
    }

    public function show(Request $request, SprintSnapshot $sprintSnapshot): View
    {
        return view('sprintSnapshot.show', [
            'sprintSnapshot' => $sprintSnapshot,
        ]);
    }
}
