<?php

namespace App\Http\Controllers\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Http\Controllers\Controller;
use App\Models\Sprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallboardController extends Controller
{
    public function index()
    {
        $sprint = Sprint::query()
            ->whereNull('closed_at')
            ->orderBy('starts_at', 'desc')
            ->first();

        if (!$sprint) {
            // fallback: show latest sprint even if closed
            $sprint = Sprint::query()->orderBy('starts_at', 'desc')->first();
        }

        if (!$sprint) {
            abort(404, 'No sprints exist yet.');
        }

        return redirect()->route('wallboard.sprint', $sprint);
    }

    public function sprint(Sprint $sprint, Request $request, SprintSummaryQuery $summaryQuery, BurndownSeriesQuery $burndownQuery)
    {
        // Burndown defaults: ad_hoc + end (end gives a final point if sprint is closed)
        $types = $request->filled('types')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $request->query('types')))))
            : ['ad_hoc', 'end'];

        $allowed = ['start', 'end', 'ad_hoc'];
        $types = array_values(array_intersect($types, $allowed));
        if (empty($types)) $types = ['ad_hoc', 'end'];

        $summary = $summaryQuery->run($sprint);

        // If sprint has no end snapshot yet, still show something (wallboard is “live”)
        // Use burndown series even if end snapshot missing (it will show ad_hoc points).
        $series = $burndownQuery->run($sprint, $types);

        $latestPoint = $series->last();

        return view('wallboard.sprint', [
            'sprint' => $sprint,
            'summary' => $summary,
            'series' => $series->values(),
            'latestPoint' => $latestPoint,
            'refreshSeconds' => 60, // tune for TV
        ]);
    }

    public function sync(\App\Models\Sprint $sprint): JsonResponse
    {
        // call your existing reconcile + snapshot commands/services
        // (wire these to your real action names)
        // app(\App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction::class)->handle($sprint);
        // app(\App\Domains\Sprints\Actions\TakeSprintSnapshotAction::class)->handle($sprint, 'ad_hoc', 'manual');

        return response()->json(['ok' => true, 'message' => 'Sync triggered.']);
    }

}
