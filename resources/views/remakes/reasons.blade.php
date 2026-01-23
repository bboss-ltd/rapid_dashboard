<x-layouts.app>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remake Reasons</h2>
                <div class="text-sm text-gray-500">Daily breakdown aligned to factory flow</div>
            </div>
            <a href="{{ route('remakes.index') }}" class="inline-flex items-center rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                Back to remakes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if(session('status'))
                    <div class="mb-4 bg-green-50 text-green-800 px-4 py-2 rounded border border-green-200 text-sm">{{ session('status') }}</div>
                @endif
                <div class="flex flex-wrap items-end gap-4">
                    <form method="get" id="reasonFilters" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-300">Day</label>
                            <input type="date" name="date" value="{{ $day->toDateString() }}" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        </div>
                        <div class="flex items-center gap-2 mt-6">
                            <input type="checkbox" id="showRemoved" name="show_removed" value="1" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                   @checked($showRemoved)>
                            <label for="showRemoved" class="text-sm text-gray-600 dark:text-gray-300">Show removed</label>
                        </div>
                        <div class="hidden">
                            <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm">Filter</button>
                        </div>
                    </form>
                    <div class="ml-auto text-sm text-gray-500">
                        @if($sprint)
                            Active sprint: {{ $sprint->name ?? 'Sprint '.$sprint->id }}
                        @else
                            No active sprint detected
                        @endif
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="{{ $hasPrev ? route('remakes.reasons', array_merge(request()->all(), ['date' => $prevDay->toDateString()])) : '#' }}"
                       class="px-3 py-1.5 rounded border text-sm {{ $hasPrev ? 'border-gray-300 text-gray-700 hover:bg-gray-50' : 'border-gray-200 text-gray-400 cursor-not-allowed' }}"
                       @if(!$hasPrev) aria-disabled="true" @endif>
                        Previous day
                    </a>
                    <a href="{{ $hasNext ? route('remakes.reasons', array_merge(request()->all(), ['date' => $nextDay->toDateString()])) : '#' }}"
                       class="px-3 py-1.5 rounded border text-sm {{ $hasNext ? 'border-gray-300 text-gray-700 hover:bg-gray-50' : 'border-gray-200 text-gray-400 cursor-not-allowed' }}"
                       @if(!$hasNext) aria-disabled="true" @endif>
                        Next day
                    </a>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    Total remakes in scope: <span class="text-gray-800 font-semibold">{{ $total }}</span>
                </div>
            </div>

            @php
                $hasResults = collect($counts)->some(fn ($count) => $count > 0);
            @endphp

            @if(!$hasResults)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-500">
                    No remakes found for this day.
                </div>
            @endif

            @foreach($groups as $label => $items)
                @php
                    $count = $counts[$label] ?? 0;
                    $percent = $percents[$label] ?? 0;
                @endphp
                @if($count > 0)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 px-6 py-4">
                            <div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $label }}</div>
                                <div class="text-sm text-gray-500">{{ $count }} remakes ({{ $percent }}%)</div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left">ID</th>
                                    <th class="px-4 py-3 text-left">Sprint</th>
                                    <th class="px-4 py-3 text-left">Card</th>
                                    <th class="px-4 py-3 text-left">Reason label</th>
                                    <th class="px-4 py-3 text-left">Points</th>
                                    <th class="px-4 py-3 text-left">First seen</th>
                                    <th class="px-4 py-3 text-right">Details</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($items as $remake)
                                    @php
                                        $points = $remake->label_points ?? $remake->estimate_points ?? 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="px-4 py-3">#{{ $remake->id }}</td>
                                        <td class="px-4 py-3">
                                            {{ $remake->sprint?->name ?? 'Sprint '.$remake->sprint_id }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($remake->card)
                                                {{ $remake->card->name ?? 'Card '.$remake->card_id }}
                                            @else
                                                {{ $remake->trello_card_id }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $remake->reason_label ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">{{ $points }}</td>
                                        <td class="px-4 py-3 text-gray-500">
                                            {{ optional($remake->first_seen_at)->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a class="text-blue-600 hover:underline" href="{{ route('remakes.show', $remake) }}">
                                                Details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach

            @if(!empty($removedGroups))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 px-6 py-4">
                        <div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">Removed labels</div>
                            <div class="text-sm text-gray-500">Excluded from remake reasons calculations</div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Sprint</th>
                                <th class="px-4 py-3 text-left">Card</th>
                                <th class="px-4 py-3 text-left">Remove label</th>
                                <th class="px-4 py-3 text-left">Points</th>
                                <th class="px-4 py-3 text-left">First seen</th>
                                <th class="px-4 py-3 text-right">Details</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($removedGroups as $group)
                                @foreach($group['items'] as $remake)
                                    @php
                                        $points = $remake->label_points ?? $remake->estimate_points ?? 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <td class="px-4 py-3">#{{ $remake->id }}</td>
                                        <td class="px-4 py-3">
                                            {{ $remake->sprint?->name ?? 'Sprint '.$remake->sprint_id }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($remake->card)
                                                {{ $remake->card->name ?? 'Card '.$remake->card_id }}
                                            @else
                                                {{ $remake->trello_card_id }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $remake->label_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">{{ $points }}</td>
                                        <td class="px-4 py-3 text-gray-500">
                                            {{ optional($remake->first_seen_at)->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a class="text-blue-600 hover:underline" href="{{ route('remakes.show', $remake) }}">
                                                Details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const filterForm = document.getElementById('reasonFilters');
                if (!filterForm) return;
                const inputs = filterForm.querySelectorAll('input');
                inputs.forEach((el) => {
                    el.addEventListener('change', () => {
                        filterForm.submit();
                    });
                });
            })();
        </script>
    @endpush
</x-layouts.app>
