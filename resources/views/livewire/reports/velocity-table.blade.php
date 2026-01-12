<div class="flex flex-col gap-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Velocity by Sprint</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Computed from END snapshots (immutable reporting).</p>
        </div>

        <div class="flex gap-2">
            <a class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm text-zinc-800 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800"
               href="/reports/velocity.csv">
                Download CSV
            </a>
            <a class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm text-zinc-800 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800"
               href="/reports/velocity.json">
                View JSON
            </a>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-3">
        <input
            wire:model.live="search"
            placeholder="Search sprint name…"
            class="w-full md:flex-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 px-3 py-2"
        />

        <select
            wire:model.live="status"
            class="w-full md:w-48 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 px-3 py-2"
        >
            <option value="all">All</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
        </select>

        <select
            wire:model.live="take"
            class="w-full md:w-48 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 px-3 py-2"
        >
            <option value="0">All sprints</option>
            <option value="10">Last 10</option>
            <option value="20">Last 20</option>
            <option value="30">Last 30</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900/40">
            <tr class="text-left text-zinc-700 dark:text-zinc-200">
                @php
                    use Illuminate\Support\Str;$th = "px-4 py-3 font-semibold select-none cursor-pointer";
                    $thPlain = "px-4 py-3 font-semibold";
                @endphp

                <th class="{{ $th }}" wire:click="sortBy('sprint_name')">
                    Sprint
                </th>

                <th class="{{ $th }}" wire:click="sortBy('starts_at')">
                    Start
                </th>

                <th class="{{ $th }}" wire:click="sortBy('ends_at')">
                    End
                </th>

                <th class="{{ $th }}" wire:click="sortBy('scope_points')">
                    Scope
                </th>

                <th class="{{ $th }}" wire:click="sortBy('completed_points')">
                    Done
                </th>

                <th class="{{ $th }}" wire:click="sortBy('remaining_points')">
                    Remaining
                </th>

                <th class="{{ $thPlain }} text-right">
                    Action
                </th>
            </tr>
            </thead>

            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse($rows as $r)
                <tr class="text-zinc-800 dark:text-zinc-100">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $r['sprint_name'] }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-300">#{{ $r['sprint_id'] }}</div>
                    </td>

                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                        {{ $r['starts_at'] ? Str::of($r['starts_at'])->substr(0, 10) : '—' }}
                    </td>

                    <td class="px-4 py-3 text-zinc-700 dark:text-zinc-200">
                        {{ $r['ends_at'] ? Str::of($r['ends_at'])->substr(0, 10) : '—' }}
                    </td>

                    <td class="px-4 py-3">{{ $r['scope_points'] }}</td>
                    <td class="px-4 py-3">{{ $r['completed_points'] }}</td>
                    <td class="px-4 py-3">{{ $r['remaining_points'] }}</td>

                    <td class="px-4 py-3 text-right">
                        <a class="text-indigo-600 dark:text-indigo-300 hover:underline"
                           href="{{ route('sprints.show', $r['sprint_id']) }}" wire:navigate>
                            Open sprint
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-zinc-600 dark:text-zinc-300">
                        No results.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $rows->links() }}
    </div>
</div>
