<x-layouts.app :title="__('Snapshot')">
    <div class="flex flex-col gap-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Snapshot</h1>
                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <div><span class="font-semibold">Sprint:</span> {{ $sprint->name }}</div>
                    <div>
                        <span class="font-semibold">Taken:</span>
                        <x-ui.datetime :value="$snapshot->taken_at" :format="config('display.datetime_seconds')" />
                    </div>
                    <div><span class="font-semibold">Type:</span> {{ $snapshot->type }} / {{ $snapshot->source }}</div>
                </div>
            </div>

            <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="{{ route('sprints.snapshots.index', $sprint) }}" wire:navigate>
                Back to snapshots
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                <tr class="text-left text-zinc-700 dark:text-zinc-200">
                    <th class="px-4 py-3 font-semibold">Done</th>
                    <th class="px-4 py-3 font-semibold">Card</th>
                    <th class="px-4 py-3 font-semibold">Points</th>
                    <th class="px-4 py-3 font-semibold">List ID</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($cards as $row)
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        <td class="px-4 py-3">
                            @if($row->is_done)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-1 text-xs">Yes</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-1 text-xs">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $row->card->name ?? '(missing card)' }}</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ $row->card->trello_card_id ?? '' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $row->estimate_points ?? 0 }}</td>
                        <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">{{ $row->trello_list_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-zinc-600 dark:text-zinc-300">No cards.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $cards->links() }}
    </div>
</x-layouts.app>
