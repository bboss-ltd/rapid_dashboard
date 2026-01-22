<?php

namespace App\Http\Controllers\Wallboard;

use App\Domains\Reporting\Queries\BurndownSeriesQuery;
use App\Domains\Reporting\Queries\SprintSummaryQuery;
use App\Domains\Sprints\Actions\ReconcileSprintBoardStateAction;
use App\Domains\Sprints\Actions\TakeSprintSnapshotAction;
use App\Domains\TrelloSync\Actions\PollBoardActionsAction;
use App\Http\Controllers\Controller;
use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $activeByStatus = Sprint::query()
            ->where('status', 'active')
            ->whereNull('closed_at')
            ->orderByDesc('starts_at')
            ->get();

        if ($activeByStatus->count() === 1) {
            return redirect()->route('wallboard.sprint', $activeByStatus->first());
        }

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
        $remakeStats = $this->buildRemakeStats($sprint, $types);
        $remakeReasonStats = $this->buildRemakeReasonStats($sprint);

        return view('wallboard.sprint', [
            'sprint' => $sprint,
            'summary' => $summary,
            'series' => $series->values(),
            'latestPoint' => $latestPoint,
            'remakeStats' => $remakeStats,
            'remakeReasonStats' => $remakeReasonStats,
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
    public function sync(
        Sprint $sprint,
        ReconcileSprintBoardStateAction $reconcile,
        TakeSprintSnapshotAction $take,
        PollBoardActionsAction $pollActions
    ): JsonResponse
    {
        $pollActions->run($sprint);
        $reconcileSnap = $reconcile->run($sprint); // may be null
        $snap = $take->run($sprint, 'ad_hoc', 'wallboard');

        return response()->json([
            'ok' => true,
            'message' => 'Sync complete.',
            'snapshot_id' => $snap->id,
            'reconcile_snapshot_id' => $reconcileSnap?->id,
        ]);
    }

    /**
     * Count remakes by Trello card creation time (derived from card id).
     */
    private function buildRemakeStats(Sprint $sprint, array $types): array
    {
        $stats = [
            'total' => 0,
            'today' => 0,
            'sprint' => 0,
            'month' => 0,
            'prev_today' => 0,
            'prev_sprint' => null,
            'prev_month' => 0,
            'trend_today' => 'neutral',
            'trend_sprint' => 'neutral',
            'trend_month' => 'neutral',
        ];

        $remakesListId = $sprint->remakes_list_id;
        if (!$remakesListId) {
            return $stats;
        }

        $latest = $sprint->snapshots()
            ->whereIn('type', $types)
            ->latest('taken_at')
            ->with(['cards.card:id,trello_card_id'])
            ->first();

        if (!$latest) {
            return $stats;
        }

        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $yesterdayEnd = $now->copy()->subDay()->endOfDay();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $sprintStart = $sprint->starts_at ?? $todayStart;
        $sprintEnd = $sprint->ends_at && $sprint->ends_at < $now ? $sprint->ends_at : $now;

        $prevSprint = Sprint::query()
            ->where('starts_at', '<', $sprintStart)
            ->orderByDesc('starts_at')
            ->first();
        $prevSprintStart = $prevSprint?->starts_at;
        $prevSprintEnd = $prevSprint?->ends_at ?? $prevSprintStart;

        $currentRemakes = 0;
        foreach ($latest->cards as $row) {
            if ($row->trello_list_id !== $remakesListId) {
                continue;
            }
            $currentRemakes++;
        }

        $remakeDates = $this->remakeDatesForSprint($sprint);

        $stats['total'] = $currentRemakes;
        $stats['today'] = $this->countBetween($remakeDates, $todayStart, $todayEnd);
        $stats['prev_today'] = $this->countBetween($remakeDates, $yesterdayStart, $yesterdayEnd);
        $stats['month'] = $this->countBetween($remakeDates, $monthStart, $monthEnd);
        $stats['prev_month'] = $this->countBetween($remakeDates, $lastMonthStart, $lastMonthEnd);
        $stats['sprint'] = $this->countBetween($remakeDates, $sprintStart, $sprintEnd);
        $prevRemakeDates = $prevSprint ? $this->remakeDatesForSprint($prevSprint) : [];
        $stats['prev_sprint'] = $prevSprintStart && $prevSprintEnd
            ? $this->countBetween($prevRemakeDates, $prevSprintStart, $prevSprintEnd)
            : null;

        $stats['trend_today'] = $this->trendLabel($stats['today'], $stats['prev_today']);

        $currentSprintDays = max(1, $sprintStart->diffInDays($sprintEnd) + 1);
        $currentSprintAvg = $stats['sprint'] / $currentSprintDays;

        $prevSprintAvg = null;
        if ($prevSprintStart && $prevSprintEnd) {
            $prevSprintDays = max(1, $prevSprintStart->diffInDays($prevSprintEnd) + 1);
            $prevSprintAvg = ($stats['prev_sprint'] ?? 0) / $prevSprintDays;
        }
        $stats['trend_sprint'] = $prevSprintAvg === null
            ? 'neutral'
            : $this->trendLabel($currentSprintAvg, $prevSprintAvg);

        $currentMonthDays = max(1, $monthStart->diffInDays($now) + 1);
        $currentMonthAvg = $stats['month'] / $currentMonthDays;
        $prevMonthDays = max(1, $lastMonthStart->daysInMonth);
        $prevMonthAvg = $stats['prev_month'] / $prevMonthDays;
        $stats['trend_month'] = $this->trendLabel($currentMonthAvg, $prevMonthAvg);

        return $stats;
    }

    private function buildRemakeReasonStats(Sprint $sprint): array
    {
        $labels = config('trello_sync.remake_reason_labels', []);
        $labelKeys = array_map(fn($l) => mb_strtolower(trim((string) $l)), $labels);

        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereNull('removed_at')
            ->where(function ($q) {
                $q->whereNull('label_points')
                    ->orWhere('label_points', '>', 0);
            })
            ->selectRaw('COALESCE(reason_label, "") as reason_label, COUNT(*) as total')
            ->groupBy('reason_label')
            ->get();

        $counts = [];
        foreach ($labels as $label) {
            $counts[$label] = 0;
        }

        $unlabeled = 0;
        $other = 0;

        foreach ($rows as $row) {
            $reason = trim((string) ($row->reason_label ?? ''));
            $count = (int) ($row->total ?? 0);
            if ($reason === '') {
                $unlabeled += $count;
                continue;
            }

            $key = mb_strtolower($reason);
            $idx = array_search($key, $labelKeys, true);
            if ($idx !== false) {
                $label = $labels[$idx];
                $counts[$label] = ($counts[$label] ?? 0) + $count;
            } else {
                $other += $count;
            }
        }

        if ($unlabeled > 0) {
            $counts['Unlabeled'] = $unlabeled;
        }
        if ($other > 0) {
            $counts['Other'] = $other;
        }

        return $counts;
    }

    /**
     * @return array<int, Carbon>
     */
    private function remakeDatesForSprint(Sprint $sprint): array
    {
        return DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereNull('removed_at')
            ->where(function ($q) {
                $q->whereNull('label_points')
                    ->orWhere('label_points', '>', 0);
            })
            ->pluck('first_seen_at')
            ->map(fn($dt) => Carbon::parse($dt))
            ->all();
    }

    /**
     * @param array<int, Carbon> $dates
     */
    private function countBetween(array $dates, Carbon $start, Carbon $end): int
    {
        $count = 0;
        foreach ($dates as $date) {
            if ($date->betweenIncluded($start, $end)) {
                $count++;
            }
        }
        return $count;
    }

    private function trendLabel(float|int $current, float|int $previous): string
    {
        if ($current > $previous) return 'bad';
        if ($current < $previous) return 'good';
        return 'neutral';
    }
}
