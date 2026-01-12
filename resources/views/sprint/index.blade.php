<x-layouts.app :title="__('Sprints')">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Sprints</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Locally stored sprint boards, snapshots, and report links.</p>
        </div>

        <form method="GET" class="flex flex-col md:flex-row gap-3">
            <input
                name="q"
                value="{{ $search }}"
                placeholder="Search by sprint name…"
                class="w-full md:flex-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 px-3 py-2"
            />

            <select
                name="status"
                class="w-full md:w-56 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 px-3 py-2"
            >
                <option value="open" @selected($status === 'open')>Open</option>
                <option value="closed" @selected($status === 'closed')>Closed</option>
                <option value="all" @selected($status === 'all')>All</option>
            </select>

            <button class="rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 px-4 py-2">
                Filter
            </button>
        </form>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                <tr class="text-left text-zinc-700 dark:text-zinc-200">
                    <th class="px-4 py-3 font-semibold">Sprint</th>
                    <th class="px-4 py-3 font-semibold">Dates</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Snapshots</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($sprints as $sprint)
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $sprint->name }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-300">Board: {{ $sprint->trello_board_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                            <div>{{ optional($sprint->starts_at)->format('Y-m-d H:i') }}</div>
                            <div>{{ optional($sprint->ends_at)->format('Y-m-d H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($sprint->closed_at)
                                <span class="inline-flex items-center rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-1 text-xs">
                                        Closed
                                    </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-1 text-xs">
                                        Open
                                    </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-zinc-700 dark:text-zinc-200">{{ $sprint->snapshots_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="{{ route('sprints.show', $sprint) }}">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-zinc-600 dark:text-zinc-300">
                            No sprints found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $sprints->links() }}
    </div>
</x-layouts.app>
