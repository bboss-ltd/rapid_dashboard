<x-layouts.app>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remake #{{ $remake->id }}</h2>
                <div class="text-sm text-gray-500">Local record with optional Trello context</div>
            </div>
            <div class="text-sm">
                <a class="text-blue-600 hover:underline" href="{{ route('remakes.index') }}">Back to list</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('status'))
                <div class="bg-green-50 text-green-800 px-4 py-3 rounded border border-green-200">{{ session('status') }}</div>
            @endif

            @php
                $reasonOptions = config('trello_sync.remake_reason_labels', []);
                $labelOptions = array_merge(
                    array_keys(config('trello_sync.remake_label_actions.remove', [])),
                    config('trello_sync.remake_label_actions.restore', [])
                );
                $reasonOptions = array_values(array_filter(array_map('trim', $reasonOptions)));
                $labelOptions = array_values(array_filter(array_map('trim', $labelOptions)));
            @endphp

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Sprint</div>
                        <div class="text-lg font-semibold">{{ $remake->sprint?->name ?? 'Sprint '.$remake->sprint_id }}</div>
                    </div>
                    <div class="text-right text-sm text-gray-500">
                        <div>Remake ID</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">#{{ $remake->id }}</div>
                    </div>
                </div>

                <div class="mt-6 grid md:grid-cols-2 gap-4 text-sm">
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Trello Card ID</div>
                        <div class="font-medium break-all">{{ $remake->trello_card_id ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Card Name</div>
                        <div class="font-medium">{{ $remake->card?->name ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Reason label</div>
                        <div class="font-medium">{{ $remake->reason_label ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Label name</div>
                        <div class="font-medium">{{ $remake->label_name ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Estimate points</div>
                        <div class="font-medium">{{ $remake->estimate_points ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Label points</div>
                        <div class="font-medium">{{ $remake->label_points ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold">Edit (local only)</h3>
                        <div class="text-xs text-gray-500">These changes do not sync back to Trello.</div>
                    </div>
                </div>
                <form method="post" action="{{ route('remakes.update', $remake) }}" class="mt-4 grid md:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Reason label</label>
                        <select name="reason_label" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">None</option>
                            @foreach($reasonOptions as $option)
                                <option value="{{ $option }}" @selected(old('reason_label', $remake->reason_label) === $option)>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Reason label color</label>
                        <input name="reason_label_color" value="{{ old('reason_label_color', $remake->reason_label_color) }}"
                               class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"/>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Label name</label>
                        <select name="label_name" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">None</option>
                            @foreach($labelOptions as $option)
                                <option value="{{ $option }}" @selected(old('label_name', $remake->label_name) === $option)>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Label points</label>
                        <input type="number" name="label_points" value="{{ old('label_points', $remake->label_points) }}"
                               class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"/>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Estimate points</label>
                        <input type="number" name="estimate_points" value="{{ old('estimate_points', $remake->estimate_points) }}"
                               class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"/>
                    </div>

                    <div class="md:col-span-2">
                        <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm">Save local changes</button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Trello context</h3>
                @if($trelloCard)
                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                            <div class="text-gray-500">Card name</div>
                            <div class="font-medium">{{ $trelloCard['name'] ?? '—' }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                            <div class="text-gray-500">URL</div>
                            <div class="font-medium">
                                @if(!empty($trelloCard['url']))
                                    <a class="text-blue-600 hover:underline" href="{{ $trelloCard['url'] }}" target="_blank" rel="noreferrer">Open Trello</a>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div class="md:col-span-2 rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                            <div class="text-gray-500">Labels</div>
                            <div class="font-medium">
                                @php($labels = $trelloCard['labels'] ?? [])
                                @if(is_array($labels) && count($labels))
                                    {{ collect($labels)->pluck('name')->filter()->join(', ') }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-500">Trello card not available.</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
