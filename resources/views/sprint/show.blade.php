<x-layouts.app :title="$sprint->name">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $sprint->name }}</h1>

                    @if($sprint->closed_at)
                        <span class="inline-flex items-center rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-1 text-xs">Closed</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-1 text-xs">Open</span>
                    @endif
                </div>

                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <div><span class="font-semibold">Board:</span> {{ $sprint->trello_board_id }}</div>
                    <div>
                        <span class="font-semibold">Dates:</span>
                        <x-ui.datetime :value="$sprint->starts_at" :format="config('display.datetime')" />
                        →
                        <x-ui.datetime :value="$sprint->ends_at" :format="config('display.datetime')" />
                    </div>
                    <div>
                        <span class="font-semibold">Closed:</span>
                        <x-ui.datetime :value="$sprint->closed_at" :format="config('display.datetime')" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 min-w-[280px]">
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 mb-2">Reports</div>
                <div class="flex flex-col gap-2 text-sm">
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="/reports/sprints/{{ $sprint->id }}/summary.json">Summary JSON</a>
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="/reports/sprints/{{ $sprint->id }}/burndown.json">Burndown JSON</a>
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="/reports/sprints/{{ $sprint->id }}/burndown.csv">Burndown CSV</a>
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="/reports/sprints/{{ $sprint->id }}/rollover.json">Rollover JSON</a>
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="/reports/sprints/{{ $sprint->id }}/rollover.csv">Rollover CSV</a>
                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="{{ route('sprints.snapshots.index', $sprint) }}" wire:navigate>Snapshots</a>
                </div>
            </div>
        </div>

        @php
            $scope = $latestPoint['scope_points'] ?? 0;
            $done = $latestPoint['done_points'] ?? 0;
            $remaining = $latestPoint['remaining_points'] ?? 0;
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Remaining points (latest snapshot)</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $remaining }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Done points (latest snapshot)</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $done }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Scope points (latest snapshot)</div>
                <div class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $scope }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Snapshots</h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                        <tr class="text-left text-zinc-700 dark:text-zinc-200">
                            <th class="px-3 py-2 font-semibold">Taken at</th>
                            <th class="px-3 py-2 font-semibold">Type</th>
                            <th class="px-3 py-2 font-semibold">Source</th>
                            <th class="px-3 py-2 font-semibold">View</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($snapshots as $snap)
                            <tr>
                                <td class="px-3 py-2">
                                    <x-ui.datetime :value="$snap->taken_at" :format="config('display.datetime_seconds')" />
                                </td>
                                <td class="px-3 py-2">{{ $snap->type }}</td>
                                <td class="px-3 py-2">{{ $snap->source }}</td>
                                <td class="px-3 py-2">
                                    <a class="text-indigo-600 dark:text-indigo-300 hover:underline"
                                       href="{{ route('sprints.snapshots.show', [$sprint, $snap]) }}" wire:navigate>
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-zinc-600 dark:text-zinc-300">No snapshots yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $snapshots->links() }}</div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Latest snapshot cards</h2>

                @if(!$latestSnapshot)
                    <div class="text-sm text-zinc-600 dark:text-zinc-300">
                        No snapshot exists yet. Run:
                        <code class="text-xs">php artisan sprint:snapshot {{ $sprint->id }} ad_hoc</code>
                    </div>
                @else
                    <div class="text-xs text-zinc-600 dark:text-zinc-300 mb-2">
                        <x-ui.datetime :value="$latestSnapshot->taken_at" :format="config('display.datetime_seconds')" />
                        ({{ $latestSnapshot->type }} / {{ $latestSnapshot->source }})
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                            <tr class="text-left text-zinc-700 dark:text-zinc-200">
                                <th class="px-3 py-2 font-semibold">Done</th>
                                <th class="px-3 py-2 font-semibold">Card</th>
                                <th class="px-3 py-2 font-semibold">Points</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($latestSnapshotCards as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        @if($row->is_done)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-1 text-xs">Yes</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-1 text-xs">No</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row->card->name ?? '(missing card)' }}</div>
                                        <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ $row->card->trello_card_id ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ $row->estimate_points ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-4 text-zinc-600 dark:text-zinc-300">No cards.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $latestSnapshotCards->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
