<?php

namespace App\Http\Controllers;

use App\Domains\Sprints\Actions\CreateSprintAction;
use App\Domains\Sprints\Actions\GetSprintDetailsAction;
use App\Domains\Sprints\Actions\SearchSprintsAction;
use App\Domains\Sprints\Actions\UpdateSprintAction;
use App\Http\Requests\SprintStoreRequest;
use App\Http\Requests\SprintUpdateRequest;
use App\Models\Sprint;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function index(Request $request, SearchSprintsAction $searchSprints)
    {
        $status = $request->query('status', 'open'); // open|closed|all
        $search = trim((string) $request->query('q', ''));

        $sprints = $searchSprints->run($status, $search, 15);

        return view('sprint.index', compact('sprints', 'status', 'search'));
    }

    public function show(Sprint $sprint, GetSprintDetailsAction $details)
    {
        return view('sprint.show', $details->run($sprint));
    }

    public function store(SprintStoreRequest $request, CreateSprintAction $createSprint)
    {
        $sprint = $createSprint->run($request->validated());

        $request->session()->flash('sprint.id', $sprint->id);

        return redirect()->route('sprints.index');
    }

    public function update(SprintUpdateRequest $request, Sprint $sprint, UpdateSprintAction $updateSprint)
    {
        $updateSprint->run($sprint, $request->validated());

        $request->session()->flash('sprint.id', $sprint->id);

        return redirect()->route('sprints.index');
    }
}
