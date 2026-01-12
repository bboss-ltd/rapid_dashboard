<?php

namespace App\Http\Controllers;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Models\Sprint;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open'); // open|closed|all
        $search = trim((string) $request->query('q', ''));

        $q = Sprint::query()
            ->orderByDesc('starts_at')
            ->withCount('snapshots');

        if ($status === 'open') {
            $q->whereNull('closed_at');
        } elseif ($status === 'closed') {
            $q->whereNotNull('closed_at');
        }

        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }

        $sprints = $q->paginate(15)->withQueryString();

        return view('sprint.index', compact('sprints', 'status', 'search'));
    }

    public function show(Sprint $sprint, BurndownSeriesQuery $burndown)
    {
        $snapshots = $sprint->snapshots()
            ->orderByDesc('taken_at')
            ->paginate(10);

        $latest = $sprint->snapshots()->latest('taken_at')->first();

        $latestCards = $latest
            ? $latest->cards()->with('card')->orderByDesc('is_done')->paginate(25, ['*'], 'cardsPage')
            : null;

        // Use burndown series to get a “live” point (ad_hoc + end)
        $series = $burndown->run($sprint, ['ad_hoc', 'end']);
        $latestPoint = $series->last();

        return view('sprint.show', [
            'sprint' => $sprint,
            'snapshots' => $snapshots,
            'latestSnapshot' => $latest,
            'latestSnapshotCards' => $latestCards,
            'latestPoint' => $latestPoint,
        ]);
    }
}
