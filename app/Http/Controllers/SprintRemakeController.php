<?php

namespace App\Http\Controllers;

use App\Http\Requests\SprintRemakeUpdateRequest;
use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SprintRemakeController extends Controller
{
    public function index(Request $request): View
    {
        $sprintId = $request->integer('sprint_id');

        $query = SprintRemake::query()
            ->with(['sprint', 'card'])
            ->whereNull('removed_at')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');

        if ($sprintId) {
            $query->where('sprint_id', $sprintId);
        }

        $remakes = $query->paginate(50)->withQueryString();
        $sprints =  Sprint::query()
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'starts_at', 'ends_at']);

        return view('remakes.index', [
            'remakes' => $remakes,
            'sprints' => $sprints,
            'selectedSprint' => $sprintId,
        ]);
    }

    public function show(Request $request, SprintRemake $remake, TrelloClient $trello): View
    {
        $remake->load(['sprint', 'card']);
        $trelloCard = null;

        if ($remake->trello_card_id) {
            try {
                $trelloCard = $trello->get("/cards/{$remake->trello_card_id}", [
                    'fields' => 'name,url,labels,idList,dateLastActivity',
                ]);
            } catch (\Throwable) {
                $trelloCard = null;
            }
        }

        return view('remakes.show', [
            'remake' => $remake,
            'trelloCard' => $trelloCard,
        ]);
    }

    public function update(SprintRemakeUpdateRequest $request, SprintRemake $remake): RedirectResponse
    {
        $data = $request->validated();
        $now = Carbon::now();

        $labelName = $data['label_name'] ?? null;
        $reasonLabel = $data['reason_label'] ?? null;

        $updates = [
            'estimate_points' => $data['estimate_points'] ?? null,
            'label_name' => $labelName !== '' ? $labelName : null,
            'label_points' => $data['label_points'] ?? null,
            'reason_label' => $reasonLabel !== '' ? $reasonLabel : null,
            'reason_label_color' => $data['reason_label_color'] ?? null,
            'last_seen_at' => $now,
        ];

        if ($updates['label_name'] === null) {
            $updates['label_points'] = null;
            $updates['label_set_at'] = null;
        } elseif ($remake->label_name !== $updates['label_name']) {
            $updates['label_set_at'] = $now;
        }

        if ($updates['reason_label'] === null) {
            $updates['reason_label_color'] = null;
            $updates['reason_set_at'] = null;
        } elseif ($remake->reason_label !== $updates['reason_label']) {
            $updates['reason_set_at'] = $now;
        }

        $remake->fill($updates)->save();

        return redirect()
            ->route('remakes.show', $remake)
            ->with('status', 'Remake updated (local only).');
    }
}
