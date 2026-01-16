<?php

namespace App\Http\Controllers\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Http\Controllers\Controller;
use App\Models\Sprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WallboardController extends Controller
{
    /**
     * Wallboard entry-point.
     *
     * Behaviour:
     * - If there is an *active* sprint (now between starts_at/ends_at and not closed) -> show it
     * - Else, if there is an open sprint (closed_at null) -> show the most recent
     * - Else, show a simple “no active sprint” page (keeps the TV useful)
     */
    public function index()
    {
        $active = Sprint::query()->active()->first();

        if ($active) {
            return redirect()->route('wallboard.sprint', $active);
        }

        $open = Sprint::query()
            ->whereNull('closed_at')
            ->orderBy('starts_at', 'desc')
            ->first();

        if ($open) {
            return redirect()->route('wallboard.sprint', $open);
        }

        $next = Sprint::query()
            ->whereNull('closed_at')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->take(3)
            ->get();

        // Avoid introducing a new Blade view here (keeps patch self-contained).
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Wallboard</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#0b0f19;color:#e8eefc}'
            . '.wrap{padding:32px}.title{font-size:40px;font-weight:800}.sub{opacity:.85;margin-top:10px}'
            . '.card{margin-top:24px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:18px}'
            . 'ul{margin:10px 0 0 18px;opacity:.95}</style></head><body><div class="wrap">'
            . '<div class="title">No active sprint</div>'
            . '<div class="sub">There is currently no sprint that is active (by dates) and open.</div>'
            . '<div class="card"><div style="font-weight:700;margin-bottom:6px;">Next sprints</div>';

        if ($next->isEmpty()) {
            $html .= '<div class="sub">No upcoming sprints found locally. Run <code>php artisan trello:sprints:sync-registry</code> to import them.</div>';
        } else {
            $html .= '<ul>';
            foreach ($next as $s) {
                $html .= '<li>' . e($s->name) . ' — ' . e(optional($s->starts_at)->format('d/m/Y')) . ' → ' . e(optional($s->ends_at)->format('d/m/Y')) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div></div></body></html>';

        return response($html);
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

    /**
     * Manual re-sync (wallboard button).
     *
     * We:
     * - Reconcile drift (creates a reconcile snapshot only if needed)
     * - Take an ad_hoc snapshot so the burndown updates immediately
     */
    public function sync(Sprint $sprint, ReconcileSprintBoardStateAction $reconcile, TakeSprintSnapshotAction $take): JsonResponse
    {
        $reconcileSnap = $reconcile->run($sprint); // may be null
        $snap = $take->run($sprint, 'ad_hoc', 'wallboard');

        return response()->json([
            'ok' => true,
            'message' => 'Sync complete.',
            'snapshot_id' => $snap->id,
            'reconcile_snapshot_id' => $reconcileSnap?->id,
        ]);
    }
}
