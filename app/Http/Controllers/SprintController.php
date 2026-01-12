<?php

namespace App\Http\Controllers;

use App\Http\Requests\SprintStoreRequest;
use App\Http\Requests\SprintUpdateRequest;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SprintController extends Controller
{
    public function index(Request $request): View
    {
        $sprints = Sprint::all();

        return view('sprint.index', [
            'sprints' => $sprints,
        ]);
    }

    public function create(Request $request): View
    {
        return view('sprint.create');
    }

    public function store(SprintStoreRequest $request): RedirectResponse
    {
        $sprint = Sprint::create($request->validated());

        $request->session()->flash('sprint.id', $sprint->id);

        return redirect()->route('sprints.index');
    }

    public function show(Request $request, Sprint $sprint): View
    {
        return view('sprint.show', [
            'sprint' => $sprint,
        ]);
    }

    public function edit(Request $request, Sprint $sprint): View
    {
        return view('sprint.edit', [
            'sprint' => $sprint,
        ]);
    }

    public function update(SprintUpdateRequest $request, Sprint $sprint): RedirectResponse
    {
        $sprint->update($request->validated());

        $request->session()->flash('sprint.id', $sprint->id);

        return redirect()->route('sprints.index');
    }

    public function destroy(Request $request, Sprint $sprint): RedirectResponse
    {
        $sprint->delete();

        return redirect()->route('sprints.index');
    }
}
