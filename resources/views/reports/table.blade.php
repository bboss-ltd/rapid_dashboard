<x-layouts.app :title="$table['title']">
    <div class="flex flex-col gap-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $table['title'] }}</h1>
                @if(!empty($table['subtitle']))
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $table['subtitle'] }}</p>
                @endif
            </div>

            @if(!empty($table['downloads']))
                <div class="flex gap-2">
                    @foreach($table['downloads'] as $dl)
                        <a class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-sm text-zinc-800 dark:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800"
                           href="{{ $dl['href'] }}">
                            Download {{ $dl['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/40">
                <tr class="text-left text-zinc-700 dark:text-zinc-200">
                    @foreach($table['columns'] as $col)
                        <th class="px-4 py-3 font-semibold">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse($table['rows'] as $row)
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        @foreach($table['columns'] as $col)
                            <td class="px-4 py-3">
                                {{ data_get($row, $col['key']) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($table['columns']) }}" class="px-4 py-6 text-zinc-600 dark:text-zinc-300">
                            No data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
