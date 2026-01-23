<?php

namespace App\Http\Controllers;

use App\Http\Requests\SprintRemakeUpdateRequest;
use App\Domains\Wallboard\Repositories\RemakeStatsRepository;
use App\Models\Sprint;
use App\Models\SprintRemake;
use App\Services\Trello\TrelloClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SprintRemakeController extends Controller
{
    public function index(Request $request): View
    {
        $filterMode = $request->input('filter_mode', 'sprint');
        $filterMode = in_array($filterMode, ['sprint', 'day', 'range'], true) ? $filterMode : 'sprint';

        $sprintId = $request->integer('sprint_id');
        if ($filterMode === 'sprint' && !$sprintId) {
            $sprintId = Sprint::active()->value('id');
        }
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 50;
        $sort = $request->input('sort');
        $direction = strtolower((string) $request->input('dir', 'asc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        $search = trim((string) $request->input('search', ''));

        $query = SprintRemake::query()
            ->with(['sprint', 'card'])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');

        if ($filterMode === 'sprint' && $sprintId) {
            $query->where('sprint_id', $sprintId);
        }
        $query->whereNull('removed_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('trello_card_id', 'like', "%{$search}%")
                    ->orWhere('label_name', 'like', "%{$search}%")
                    ->orWhere('reason_label', 'like', "%{$search}%")
                    ->orWhereHas('card', function ($card) use ($search) {
                        $card->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $date = $request->input('date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($filterMode === 'day') {
            if (!is_string($date) || $date === '') {
                $date = Carbon::today()->toDateString();
            }
            if (is_string($date) && $date !== '') {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
                $query->whereBetween('first_seen_at', [$day, $day->copy()->endOfDay()]);
            } catch (\Throwable) {
            }
            }
        } elseif ($filterMode === 'range') {
            if (is_string($startDate) && $startDate !== '') {
                try {
                    $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                    $query->where('first_seen_at', '>=', $start);
                } catch (\Throwable) {
                }
            }
            if (is_string($endDate) && $endDate !== '') {
                try {
                    $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
                    $query->where('first_seen_at', '<=', $end);
                } catch (\Throwable) {
                }
            }
        }

        if ($sort === 'reason') {
            $query->reorder()
                ->orderByRaw('COALESCE(reason_label, label_name) '.$direction)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id');
        }

        $remakes = $query->paginate($perPage)->withQueryString();
        $sprints =  Sprint::query()
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'starts_at', 'ends_at']);

        return view('remakes.index', [
            'remakes' => $remakes,
            'sprints' => $sprints,
            'selectedSprint' => $sprintId,
            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $direction,
            'filterMode' => $filterMode,
            'search' => $search,
        ]);
    }

    public function reasons(Request $request, RemakeStatsRepository $remakeStats): View
    {
        $dateInput = $request->input('date');
        $day = Carbon::today();
        $search = trim((string) $request->input('search', ''));
        if (is_string($dateInput) && $dateInput !== '') {
            try {
                $day = Carbon::createFromFormat('Y-m-d', $dateInput)->startOfDay();
            } catch (\Throwable) {
                $day = Carbon::today();
            }
        }

        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $sprint = Sprint::active()->first();

        $query = SprintRemake::query()
            ->with(['sprint', 'card'])
            ->whereBetween('first_seen_at', [$start, $end]);

        if ($sprint) {
            $query->where('sprint_id', $sprint->id);
        }

        $query->whereNull('removed_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('trello_card_id', 'like', "%{$search}%")
                    ->orWhere('label_name', 'like', "%{$search}%")
                    ->orWhere('reason_label', 'like', "%{$search}%")
                    ->orWhereHas('card', function ($card) use ($search) {
                        $card->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $remakes = $query->orderByDesc('first_seen_at')->get();

        $baseQuery = SprintRemake::query();
        if ($sprint) {
            $baseQuery->where('sprint_id', $sprint->id);
        }
        $firstSeen = $baseQuery->orderBy('first_seen_at')->value('first_seen_at');
        $earliestDay = $firstSeen ? Carbon::parse($firstSeen)->startOfDay() : $day->copy()->startOfDay();
        $latestDay = Carbon::today()->startOfDay();

        $prevDay = $day->copy()->subDay();
        $nextDay = $day->copy()->addDay();

        $hasPrev = false;
        if ($prevDay->greaterThanOrEqualTo($earliestDay)) {
            $hasPrev = (clone $baseQuery)
                ->whereBetween('first_seen_at', [$prevDay->copy()->startOfDay(), $prevDay->copy()->endOfDay()])
                ->exists();
        }

        $hasNext = false;
        if ($nextDay->lessThanOrEqualTo($latestDay)) {
            $hasNext = (clone $baseQuery)
                ->whereBetween('first_seen_at', [$nextDay->copy()->startOfDay(), $nextDay->copy()->endOfDay()])
                ->exists();
        }

        $flow = $remakeStats->reasonFlow();
        $flowMap = $remakeStats->reasonFlowMap();
        $removeLabels = $remakeStats->removeLabelNames();

        $groups = array_fill_keys($flow, []);
        $total = 0;
        $removedGroups = [];

        foreach ($remakes as $remake) {
            if ($remakeStats->isRemoveLabel($remake->label_name)) {
                $removeKey = $remakeStats->reasonKey($remake->label_name) ?? 'removed';
                $removedGroups[$removeKey]['label'] = $remake->label_name ?: 'Removed';
                $removedGroups[$removeKey]['items'][] = $remake;
                continue;
            }

            $reasonKey = $remakeStats->reasonKey($remake->reason_label);
            if ($reasonKey === null) {
                $groups['Unlabelled'][] = $remake;
                $total++;
                continue;
            }

            if (array_key_exists($reasonKey, $flowMap)) {
                $label = $flowMap[$reasonKey];
                $groups[$label][] = $remake;
                $total++;
            }
        }

        $counts = [];
        $percents = [];
        foreach ($groups as $label => $items) {
            $count = is_countable($items) ? count($items) : 0;
            $counts[$label] = $count;
            $percents[$label] = $total > 0 ? round(($count / $total) * 100) : 0;
        }

        return view('remakes.reasons', [
            'day' => $day,
            'start' => $start,
            'end' => $end,
            'sprint' => $sprint,
            'groups' => $groups,
            'counts' => $counts,
            'percents' => $percents,
            'total' => $total,
            'showRemoved' => $request->boolean('show_removed'),
            'removedGroups' => $removedGroups,
            'hasPrev' => $hasPrev,
            'hasNext' => $hasNext,
            'prevDay' => $prevDay,
            'nextDay' => $nextDay,
            'search' => $search,
        ]);
    }

    public function show(Request $request, SprintRemake $remake, TrelloClient $trello): View
    {
        $remake->load(['sprint', 'card']);
        $trelloCard = null;
        $trelloActionsAllowed = $this->trelloActionsAllowed($request);

        if ($remake->trello_card_id) {
            try {
                $cacheKey = "trello.card.{$remake->trello_card_id}";
                $trelloCard = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($trello, $remake) {
                    return $trello->get("/cards/{$remake->trello_card_id}", [
                        'fields' => 'name,url,labels,idList,dateLastActivity',
                    ]);
                });
            } catch (\Throwable) {
                $trelloCard = null;
            }
        }

        return view('remakes.show', [
            'remake' => $remake,
            'trelloCard' => $trelloCard,
            'trelloActionsAllowed' => $trelloActionsAllowed,
        ]);
    }

    public function update(SprintRemakeUpdateRequest $request, SprintRemake $remake, TrelloClient $trello): RedirectResponse
    {
        if ($remake->removed_at) {
            return redirect()
                ->route('remakes.show', $remake)
                ->with('status', 'This remake was removed; edits are disabled.');
        }

        $data = $request->validated();
        $now = Carbon::now();

        $selected = trim((string) ($data['remake_label'] ?? ''));
        $points = $data['points'] ?? null;

        $reasonLabels = $this->normalizeLabels(config('trello_sync.remake_reason_labels', []));
        $removeMap = $this->normalizeLabelPoints(config('trello_sync.remake_label_actions.remove', []));
        $removeLabels = array_keys($removeMap);

        $normalized = $this->normalizeLabel($selected);
        $isReason = $selected !== '' && in_array($normalized, $reasonLabels, true);
        $isRemove = $selected !== '' && array_key_exists($normalized, $removeMap);

        if ($selected !== '' && !$isReason && !$isRemove) {
            return redirect()
                ->route('remakes.show', $remake)
                ->with('status', 'Selected label is not a valid remake label.');
        }

        $reasonColor = $isReason
            ? $this->resolveReasonColor($trello, $remake->sprint?->trello_board_id, $selected)
            : null;

        $updates = [
            'estimate_points' => $isReason ? $remake->estimate_points : ($points ?? $remake->estimate_points),
            'label_name' => $isRemove ? $selected : null,
            'label_points' => $isRemove ? ($points ?? $removeMap[$normalized] ?? null) : null,
            'reason_label' => $isReason ? $selected : null,
            'reason_label_color' => $isReason ? $reasonColor : null,
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

    public function refreshIndex(Request $request, TrelloClient $trello): RedirectResponse
    {
        $query = SprintRemake::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id');

        $sprintId = $request->integer('sprint_id');
        if ($sprintId) {
            $query->where('sprint_id', $sprintId);
        }
        $query->whereNull('removed_at');

        $cardIds = $query->pluck('trello_card_id')->filter()->unique()->values()->all();
        $fetched = $this->refreshTrelloCards($trello, $cardIds);

        return redirect()
            ->route('remakes.index', $request->only(['sprint_id']))
            ->with('status', "Trello refresh complete. Updated {$fetched} cards.");
    }

    public function refreshShow(SprintRemake $remake, TrelloClient $trello): RedirectResponse
    {
        $cardId = $remake->trello_card_id;
        if ($cardId) {
            $this->refreshTrelloCards($trello, [$cardId]);
        }

        return redirect()
            ->route('remakes.show', $remake)
            ->with('status', 'Trello card refreshed.');
    }

    public function updateTrello(Request $request, SprintRemake $remake, TrelloClient $trello): RedirectResponse
    {
        if (!$this->trelloActionsAllowed($request)) {
            return redirect()
                ->route('remakes.show', $remake)
                ->with('status', 'You are not authorised to update Trello from this screen.');
        }

        $validator = Validator::make($request->all(), [
            'trello_name' => ['nullable', 'string', 'max:500'],
            'trello_cover_color' => ['nullable', 'string'],
            'trello_cover_size' => ['nullable', 'string'],
            'trello_cover_brightness' => ['nullable', 'string'],
        ]);
        $data = $validator->validated();

        if (!$remake->trello_card_id) {
            return redirect()
                ->route('remakes.show', $remake)
                ->with('status', 'Missing Trello card id.');
        }

        $payload = [];
        if (!empty($data['trello_name'])) {
            $payload['name'] = $data['trello_name'];
        }

        $coverColor = $data['trello_cover_color'] ?? null;
        if ($coverColor !== null && $coverColor !== '') {
            $payload['cover'] = [
                'color' => $coverColor === 'none' ? null : $coverColor,
                'size' => $data['trello_cover_size'] ?? 'normal',
                'brightness' => $data['trello_cover_brightness'] ?? 'light',
            ];
        }

        if ($payload === []) {
            return redirect()
                ->route('remakes.show', $remake)
                ->with('status', 'No Trello changes submitted.');
        }

        $trello->put("/cards/{$remake->trello_card_id}", $payload);
        Cache::forget("trello.card.{$remake->trello_card_id}");

        return redirect()
            ->route('remakes.show', $remake)
            ->with('status', 'Trello card updated.');
    }

    /**
     * @return array<string, string>
     */
    private function fetchBoardLabelColors(TrelloClient $trello, ?string $boardId): array
    {
        if (!$boardId) {
            return [];
        }

        $cacheKey = "trello.board.labels.{$boardId}";
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($trello, $boardId) {
            try {
                $labels = $trello->get("/boards/{$boardId}/labels", [
                    'limit' => 1000,
                    'fields' => 'name,color',
                ]);
            } catch (\Throwable) {
                return [];
            }

            if (!is_array($labels)) {
                return [];
            }

            $map = [];
            foreach ($labels as $label) {
                $name = trim((string) ($label['name'] ?? ''));
                $color = trim((string) ($label['color'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $map[$this->normalizeLabel($name)] = $color;
            }
            return $map;
        });
    }

    private function resolveReasonColor(TrelloClient $trello, ?string $boardId, ?string $reasonLabel): ?string
    {
        $reasonLabel = trim((string) $reasonLabel);
        if ($reasonLabel === '') {
            return null;
        }

        $map = $this->fetchBoardLabelColors($trello, $boardId);
        $normalized = $this->normalizeLabel($reasonLabel);
        return $map[$normalized] ?? null;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label);
        return trim((string) $label);
    }

    /**
     * @param array<int, string> $labels
     * @return array<int, string>
     */
    private function normalizeLabels(array $labels): array
    {
        return array_values(array_filter(array_map(function ($label) {
            $label = $this->normalizeLabel((string) $label);
            return $label !== '' ? $label : null;
        }, $labels)));
    }

    /**
     * @param array<string, int|float|string> $labels
     * @return array<string, int>
     */
    private function normalizeLabelPoints(array $labels): array
    {
        $out = [];
        foreach ($labels as $label => $points) {
            $name = $this->normalizeLabel((string) $label);
            if ($name === '') {
                continue;
            }
            $out[$name] = (int) $points;
        }
        return $out;
    }

    private function trelloActionsAllowed(Request $request): bool
    {
        $allowed = config('trello_sync.trello_actions_allowed_emails', []);
        if (!is_array($allowed) || $allowed === []) {
            return false;
        }
        $email = strtolower((string) ($request->user()?->email ?? ''));
        $allowed = array_map(fn ($value) => strtolower(trim((string) $value)), $allowed);
        return $email !== '' && in_array($email, $allowed, true);
    }

    /**
     * @param array<int, string> $cardIds
     */
    private function refreshTrelloCards(TrelloClient $trello, array $cardIds): int
    {
        $cardIds = array_values(array_filter(array_map('trim', $cardIds)));
        if ($cardIds === []) {
            return 0;
        }

        $urls = array_map(function ($id) {
            return "/cards/{$id}?fields=name,url,labels,idList,dateLastActivity,cover";
        }, $cardIds);

        $batch = $trello->batch($urls);
        $count = 0;

        foreach ($batch as $entry) {
            $url = $entry['url'] ?? null;
            $code = (int) ($entry['code'] ?? 0);
            $body = $entry['body'] ?? null;
            if (!is_string($url) || $code !== 200 || !is_array($body)) {
                continue;
            }
            if (preg_match('/\\/cards\\/([^\\?]+)/', $url, $matches) !== 1) {
                continue;
            }
            $cardId = $matches[1] ?? null;
            if (!$cardId) {
                continue;
            }
            Cache::put("trello.card.{$cardId}", $body, now()->addMinutes(5));
            $count++;
        }

        return $count;
    }
}
