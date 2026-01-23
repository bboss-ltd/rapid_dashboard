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
                    <div class="flex items-center gap-2 mt-6">
                        <input type="checkbox" id="showRemoved" name="show_removed" value="1" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                               @checked(request()->boolean('show_removed'))>
                        <label for="showRemoved" class="text-sm text-gray-600 dark:text-gray-300">Show removed</label>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Per page</label>
                        <select name="per_page" class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            @foreach([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hidden">
                        <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm">Filter</button>
                    </div>
                    </form>

                    <form method="post" action="{{ route('remakes.refresh') }}" class="ml-auto">
                        @csrf
                        <input type="hidden" name="sprint_id" value="{{ request('sprint_id') }}">
                        <input type="hidden" name="show_removed" value="{{ request()->boolean('show_removed') ? 1 : 0 }}">
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
                            <th class="px-4 py-3 text-left">Label / Reason</th>
                            <th class="px-4 py-3 text-left">Points</th>
                            <th class="px-4 py-3 text-left">Last Seen</th>
                            <th class="px-4 py-3 text-right">Details</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($remakes as $remake)
                            @php
                                $label = $remake->reason_label ?: $remake->label_name;
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
                                </td>
                                <td class="px-4 py-3">
                                    {{ $label ?: '—' }}
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
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">No remakes found.</td>
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
                const inputs = filterForm.querySelectorAll('select, input[type="checkbox"]');
                inputs.forEach((el) => {
                    el.addEventListener('change', () => {
                        filterForm.submit();
                    });
                });
            })();
        </script>
    @endpush
</x-layouts.app>
