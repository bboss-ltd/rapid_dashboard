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

    public function buildRemakeReasonStats(Sprint $sprint, Carbon $start, Carbon $end): array
    {
        $order = $this->reasonFlow();
        $flowMap = $this->reasonFlowMap();

        $removeLabels = $this->removeLabelNames();

        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereBetween('first_seen_at', [$start, $end])
            ->whereNull('removed_at')
            ->select('reason_label', 'label_name')
            ->get();

        $counts = array_fill_keys($order, 0);

        foreach ($rows as $row) {
            $labelName = $this->normalizeLabelName($row->label_name ?? null);
            if ($labelName && in_array($labelName, $removeLabels, true)) {
                continue;
            }

            $reasonKey = $this->normalizeReasonLabelKey($row->reason_label ?? null);
            if ($reasonKey === null) {
                $counts['Unlabelled']++;
                continue;
            }

            if (array_key_exists($reasonKey, $flowMap)) {
                $label = $flowMap[$reasonKey];
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        $colors = $this->reasonColorsForSprint($sprint, $flowMap);

        return [
            'counts' => $counts,
            'colors' => $colors,
            'order' => $order,
        ];
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
        $allowedKeys = array_keys($this->reasonFlowMap());
        if ($allowedKeys === []) {
            return 0;
        }

        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereBetween('first_seen_at', [$start, $end])
            ->select('reason_label')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $label = $this->normalizeReasonLabelKey($row->reason_label ?? null);
            if ($label && in_array($label, $allowedKeys, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeLabelName(?string $label): ?string
    {
        $name = trim((string) $label);
        if ($name === '') {
            return null;
        }
        $name = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $name) ?? $name;
        $name = mb_strtolower(trim($name));
        return $name === '' ? null : $name;
    }

    private function normalizeReasonLabelKey(?string $label): ?string
    {
        $name = trim((string) $label);
        if ($name === '') {
            return null;
        }
        $name = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $name) ?? $name;
        $name = preg_replace('/\\s*\\*\\s*$/', '', $name) ?? $name;
        $name = mb_strtolower(trim($name));

        return $name === '' ? null : $name;
    }

    private function normalizeReasonLabelDisplay(?string $label): ?string
    {
        $name = trim((string) $label);
        if ($name === '') {
            return null;
        }
        $name = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $name) ?? $name;
        $name = trim($name);

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

    private function reasonColorsForSprint(Sprint $sprint, array $flowMap = []): array
    {
        $rows = DB::table('sprint_remakes')
            ->where('sprint_id', $sprint->id)
            ->whereNull('removed_at')
            ->whereNotNull('reason_label')
            ->whereNotNull('reason_label_color')
            ->orderByDesc('reason_set_at')
            ->get(['reason_label', 'reason_label_color', 'reason_set_at']);

        $colors = [];
        foreach ($rows as $row) {
            $labelKey = $this->normalizeReasonLabelKey($row->reason_label ?? null);
            if (!$labelKey) {
                continue;
            }
            if ($flowMap !== [] && !array_key_exists($labelKey, $flowMap)) {
                continue;
            }
            $display = $flowMap !== [] ? $flowMap[$labelKey] : $this->normalizeReasonLabelDisplay($row->reason_label ?? null);
            if (!$display) {
                continue;
            }
            if (!array_key_exists($display, $colors)) {
                $colors[$display] = strtolower(trim((string) $row->reason_label_color));
            }
        }

        return $colors;
    }

    /**
     * @return array<int, string>
     */
    public function reasonFlow(): array
    {
        $removeKeys = $this->removeLabelNames();
        $labels = array_values(array_filter(array_map(function ($label) {
            return $this->normalizeReasonLabelDisplay($label);
        }, config('trello_sync.remake_reason_labels', []))));

        $unique = [];
        foreach ($labels as $label) {
            $key = $this->normalizeReasonLabelKey($label);
            if (!$key || in_array($key, $removeKeys, true) || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $label;
        }

        $flow = array_values($unique);

        $extraLabels = DB::table('sprint_remakes')
            ->whereNull('removed_at')
            ->whereNotNull('reason_label')
            ->select('reason_label')
            ->distinct()
            ->get();

        $extras = [];
        foreach ($extraLabels as $row) {
            $display = $this->normalizeReasonLabelDisplay($row->reason_label ?? null);
            $key = $this->normalizeReasonLabelKey($row->reason_label ?? null);
            if (!$display || !$key || in_array($key, $removeKeys, true) || isset($unique[$key])) {
                continue;
            }
            $extras[$key] = rtrim($display) . ' *';
        }

        if ($extras !== []) {
            ksort($extras);
            $flow = array_merge($flow, array_values($extras));
        }

        $flow[] = 'Unlabelled';
        return $flow;
    }

    /**
     * @return array<string, string>
     */
    public function reasonFlowMap(): array
    {
        $map = [];
        foreach ($this->reasonFlow() as $label) {
            $key = $this->normalizeReasonLabelKey($label);
            if ($key) {
                $map[$key] = $label;
            }
        }
        return $map;
    }

    /**
     * @return array<int, string>
     */
    public function removeLabelNames(): array
    {
        return $this->normalizeLabelNames(
            array_keys(config('trello_sync.remake_label_actions.remove', []))
        );
    }

    public function isRemoveLabel(?string $labelName): bool
    {
        $labelName = $this->normalizeLabelName($labelName);
        if (!$labelName) {
            return false;
        }
        return in_array($labelName, $this->removeLabelNames(), true);
    }

    public function reasonKey(?string $label): ?string
    {
        return $this->normalizeReasonLabelKey($label);
    }
}
