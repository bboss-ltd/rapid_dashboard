<x-layouts.app>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Sprint Remakes</h2>
                <div class="text-sm text-gray-500">Most recent first</div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if(session('status'))
                    <div class="mb-4 bg-green-50 text-green-800 px-4 py-2 rounded border border-green-200 text-sm">{{ session('status') }}</div>
                @endif
                <div class="flex flex-wrap items-end gap-4">
                    <form method="get" id="remakeFilters" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Filter by</label>
                        <select name="filter_mode" id="filterMode" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="sprint" @selected($filterMode === 'sprint')>Sprint</option>
                            <option value="day" @selected($filterMode === 'day')>Single day</option>
                            <option value="range" @selected($filterMode === 'range')>Date range</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Search</label>
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Card, label, Trello id"
                               class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    </div>
                    <div class="{{ $filterMode === 'sprint' ? '' : 'hidden' }}" data-filter-field="sprint">
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Sprint</label>
                        <select name="sprint_id" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">All sprints</option>
                            @foreach($sprints as $sprint)
                                <option value="{{ $sprint->id }}" @selected((int) $selectedSprint === (int) $sprint->id)>
                                    {{ $sprint->name ?? 'Sprint '.$sprint->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="{{ $filterMode === 'day' ? '' : 'hidden' }}" data-filter-field="day">
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Single day</label>
                        <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    </div>
                    <div class="{{ $filterMode === 'range' ? '' : 'hidden' }}" data-filter-field="range-start">
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Start date</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    </div>
                    <div class="{{ $filterMode === 'range' ? '' : 'hidden' }}" data-filter-field="range-end">
                        <label class="block text-sm text-gray-600 dark:text-gray-300">End date</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Per page</label>
                        <select name="per_page" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            @foreach([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                    <input type="hidden" name="dir" value="{{ request('dir') }}">
                    <div id="applyFiltersWrap" class="hidden">
                        <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm">Apply filter</button>
                    </div>
                    </form>

                    <form method="post" action="{{ route('remakes.refresh') }}" class="ml-auto">
                        @csrf
                        <input type="hidden" name="sprint_id" value="{{ request('sprint_id') }}">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <button id="refreshRemakesBtn" class="px-4 py-2 rounded border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-900">
                            Refresh Trello data
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Sprint</th>
                            <th class="px-4 py-3 text-left">Card</th>
                            <th class="px-4 py-3 text-left">
                                @php
                                    $currentDir = request('sort') === 'reason' ? (request('dir') === 'asc' ? 'asc' : 'desc') : null;
                                    $nextDir = $currentDir === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a class="inline-flex items-center gap-1 text-gray-700 hover:text-gray-900"
                                   href="{{ route('remakes.index', array_merge(request()->all(), ['sort' => 'reason', 'dir' => $nextDir])) }}">
                                    Local Label
                                    @if($currentDir === 'asc')
                                        <span aria-hidden="true">▲</span>
                                    @elseif($currentDir === 'desc')
                                        <span aria-hidden="true">▼</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-4 py-3 text-left">Trello Label</th>
                            <th class="px-4 py-3 text-left">Points</th>
                            <th class="px-4 py-3 text-left">Last Seen</th>
                            <th class="px-4 py-3 text-right">Details</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($remakes as $remake)
                            @php
                                $label = $remake->reason_label ?: $remake->label_name;
                                $displayLabel = $label
                                    ? trim((string) (preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $label) ?? $label))
                                    : null;
                                $trelloLabel = $remake->trello_reason_label;
                                $displayTrello = $trelloLabel
                                    ? trim((string) (preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', $trelloLabel) ?? $trelloLabel))
                                    : null;
                                $knownReasons = array_values(array_filter(array_map(function ($value) {
                                    $value = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', (string) $value) ?? $value;
                                    return strtolower(trim((string) $value));
                                }, config('trello_sync.remake_reason_labels', []))));
                                $removeKeys = array_values(array_filter(array_map(function ($value) {
                                    $value = preg_replace('/^rm\\s*[:\\-]?\\s*/i', '', (string) $value) ?? $value;
                                    return strtolower(trim((string) $value));
                                }, array_keys(config('trello_sync.remake_label_actions.remove', [])))));
                                $isNewReason = $displayLabel
                                    ? (!in_array(strtolower($displayLabel), $knownReasons, true) && !in_array(strtolower($displayLabel), $removeKeys, true))
                                    : false;
                                $isNewTrello = $displayTrello
                                    ? (!in_array(strtolower($displayTrello), $knownReasons, true) && !in_array(strtolower($displayTrello), $removeKeys, true))
                                    : false;
                                $points = $remake->label_points ?? $remake->estimate_points ?? 0;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                                <td class="px-4 py-3">
                                    #{{ $remake->id }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $remake->sprint?->name ?? 'Sprint '.$remake->sprint_id }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($remake->card)
                                        {{ $remake->card->name ?? 'Card '.$remake->card_id }}
                                    @else
                                        {{ $remake->trello_card_id }}
                                    @endif
                                    @if($remake->trello_card_id)
                                        <div class="text-xs text-gray-500 mt-1">
                                            <a class="text-blue-600 hover:underline" href="{{ 'https://trello.com/c/'.$remake->trello_card_id }}" target="_blank" rel="noreferrer">
                                                Open Trello
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $displayLabel ? $displayLabel.($isNewReason ? ' *' : '') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $displayTrello ? $displayTrello.($isNewTrello ? ' *' : '') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $points }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ optional($remake->last_seen_at)->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a class="text-blue-600 hover:underline" href="{{ route('remakes.show', $remake) }}">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">No remakes found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    {{ $remakes->links() }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const btn = document.getElementById('refreshRemakesBtn');
                if (!btn) return;
                const form = btn.closest('form');
                if (!form) return;
                form.addEventListener('submit', () => {
                    btn.disabled = true;
                    btn.textContent = 'Refreshing...';
                });
            })();

            (function () {
                const filterForm = document.getElementById('remakeFilters') || document.querySelector('form[method="get"]');
                if (!filterForm) return;
                const inputs = filterForm.querySelectorAll('select, input[type="checkbox"], input[type="date"]');
                const applyWrap = document.getElementById('applyFiltersWrap');
                const modeSelect = document.getElementById('filterMode');
                inputs.forEach((el) => {
                    if (el.id === 'filterMode') {
                        return;
                    }
                    if (el.type === 'date' && modeSelect?.value === 'range') {
                        return;
                    }
                    el.addEventListener('change', () => {
                        filterForm.submit();
                    });
                });
            })();

            (function () {
                const filterForm = document.getElementById('remakeFilters');
                if (!filterForm) return;
                const mode = filterForm.querySelector('#filterMode');
                const applyWrap = document.getElementById('applyFiltersWrap');
                const sprintWrap = filterForm.querySelector('[data-filter-field="sprint"]');
                const dayWrap = filterForm.querySelector('[data-filter-field="day"]');
                const rangeStartWrap = filterForm.querySelector('[data-filter-field="range-start"]');
                const rangeEndWrap = filterForm.querySelector('[data-filter-field="range-end"]');
                const searchInput = filterForm.querySelector('input[name="search"]');

                function applyMode(value) {
                    if (sprintWrap) sprintWrap.classList.toggle('hidden', value !== 'sprint');
                    if (dayWrap) dayWrap.classList.toggle('hidden', value !== 'day');
                    if (rangeStartWrap) rangeStartWrap.classList.toggle('hidden', value !== 'range');
                    if (rangeEndWrap) rangeEndWrap.classList.toggle('hidden', value !== 'range');
                    if (applyWrap) {
                        if (value === 'range') {
                            applyWrap.classList.remove('hidden');
                        } else {
                            applyWrap.classList.add('hidden');
                        }
                    }
                }

                applyMode(mode?.value || 'sprint');
                mode?.addEventListener('change', () => {
                    applyMode(mode.value);
                    if (applyWrap) {
                        applyWrap.classList.remove('hidden');
                    }
                });

                if (searchInput && applyWrap) {
                    searchInput.addEventListener('input', () => {
                        applyWrap.classList.remove('hidden');
                    });
                }
            })();
        </script>
    @endpush
</x-layouts.app>
