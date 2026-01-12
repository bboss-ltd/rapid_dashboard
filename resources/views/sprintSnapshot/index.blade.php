<x-layouts.app :title="__('Snapshots')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Snapshots</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $sprint->name }}</p>
            </div>

            <a class="text-indigo-600 dark:text-indigo-300 hover:underline" href="{{ route('sprints.show', $sprint) }}" wire:navigate>
                Back to sprint
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                <tr class="text-left text-zinc-700 dark:text-zinc-200">
                    <th class="px-4 py-3 font-semibold">Taken at</th>
                    <th class="px-4 py-3 font-semibold">Type</th>
                    <th class="px-4 py-3 font-semibold">Source</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($snapshots as $snap)
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        <td class="px-4 py-3">{{ $snap->taken_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $snap->type }}</td>
                        <td class="px-4 py-3">{{ $snap->source }}</td>
                        <td class="px-4 py-3 text-right">
                            <a class="text-indigo-600 dark:text-indigo-300 hover:underline"
                               href="{{ route('sprints.snapshots.show', [$sprint, $snap]) }}" wire:navigate>
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-zinc-600 dark:text-zinc-300">No snapshots.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $snapshots->links() }}
    </div>
</x-layouts.app>
