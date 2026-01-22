<?php

namespace App\Domains\Wallboard\Repositories;

use App\Models\Sprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RemakeStatsRepository
{
    public function buildRemakeStats(Sprint $sprint, array $types): array
    {
        $stats = [
            'total' => 0,
            'today' => 0,
            'sprint' => 0,
            'month' => 0,
            'prev_today' => 0,
            'prev_sprint' => null,
            'prev_month' => 0,
            'requested_today' => 0,
            'accepted_today' => 0,
            'requested_prev_today' => 0,
            'accepted_prev_today' => 0,
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

        $removeLabels = $this->normalizeLabelNames(
            array_keys(config('trello_sync.remake_label_actions.remove', []))
        );
        $reasonLabels = $this->normalizeLabelNames(
            config('trello_sync.remake_reason_labels', [])
        );

        $remakeDates = $this->remakeDatesForSprint($sprint, $removeLabels);

        $stats['total'] = $currentRemakes;
        $stats['today'] = $this->countBetween($remakeDates, $todayStart, $todayEnd);
        $stats['prev_today'] = $this->countBetween($remakeDates, $yesterdayStart, $yesterdayEnd);
        $stats['month'] = $this->countBetween($remakeDates, $monthStart, $monthEnd);
        $stats['prev_month'] = $this->countBetween($remakeDates, $lastMonthStart, $lastMonthEnd);
        $stats['sprint'] = $this->countBetween($remakeDates, $sprintStart, $sprintEnd);

        $prevRemakeDates = $prevSprint ? $this->remakeDatesForSprint($prevSprint, $removeLabels) : [];
        $stats['prev_sprint'] = $prevSprintStart && $prevSprintEnd
            ? $this->countBetween($prevRemakeDates, $prevSprintStart, $prevSprintEnd)
            : null;

        $stats['requested_today'] = $this->requestedCountForDateRange($sprint, $todayStart, $todayEnd, $removeLabels);
        $stats['requested_prev_today'] = $this->requestedCountForDateRange($sprint, $yesterdayStart, $yesterdayEnd, $removeLabels);
        $stats['accepted_today'] = $this->acceptedCountForDateRange($sprint, $todayStart, $todayEnd, $reasonLabels);
        $stats['accepted_prev_today'] = $this->acceptedCountForDateRange($sprint, $yesterdayStart, $yesterdayEnd, $reasonLabels);

        $stats['trend_today'] = $this->trendLabel($stats['requested_today'], $stats['requested_prev_today']);

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

    public function buildRemakeReasonStats(Sprint $sprint): array
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
    private function remakeDatesForSprint(Sprint $sprint, array $removeLabels = []): array
    {
        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereNull('removed_at')
            ->select('first_seen_at', 'label_name')
            ->get();

        $dates = [];
        foreach ($rows as $row) {
            $label = $this->normalizeLabelName($row->label_name ?? null);
            if ($label && in_array($label, $removeLabels, true)) {
                continue;
            }
            $dates[] = Carbon::parse($row->first_seen_at);
        }

        return $dates;
    }

    private function requestedCountForDateRange(Sprint $sprint, Carbon $start, Carbon $end, array $removeLabels = []): int
    {
        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereBetween('first_seen_at', [$start, $end])
            ->select('label_name')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $label = $this->normalizeLabelName($row->label_name ?? null);
            if ($label && in_array($label, $removeLabels, true)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function acceptedCountForDateRange(Sprint $sprint, Carbon $start, Carbon $end, array $reasonLabels = []): int
    {
        if (empty($reasonLabels)) {
            return 0;
        }

        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereBetween('first_seen_at', [$start, $end])
            ->select('reason_label')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $label = $this->normalizeLabelName($row->reason_label ?? null);
            if ($label && in_array($label, $reasonLabels, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeLabelName(?string $label): ?string
    {
        $name = mb_strtolower(trim((string) $label));
        return $name === '' ? null : $name;
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function normalizeLabelNames(array $labels): array
    {
        return array_values(array_filter(array_map(function ($label) {
            $name = $this->normalizeLabelName($label);
            return $name ?? null;
        }, $labels)));
    }

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
