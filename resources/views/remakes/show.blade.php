<x-layouts.app>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remake #{{ $remake->id }}</h2>
                <div class="text-sm text-gray-500">Local record with optional Trello context</div>
            </div>
            <div class="text-sm">
                <a class="inline-flex items-center gap-2 rounded border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-900" href="{{ route('remakes.index') }}">
                    ← Back to remakes
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a class="inline-flex items-center gap-2 rounded border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-900" href="{{ route('remakes.index') }}">
                    ← Back to remakes
                </a>
            </div>
            @if(session('status'))
                <div class="bg-green-50 text-green-800 px-4 py-3 rounded border border-green-200">{{ session('status') }}</div>
            @endif

            @php
                $reasonOptions = array_values(array_filter(array_map('trim', config('trello_sync.remake_reason_labels', []))));
                $labelOptions = array_values(array_filter(array_map('trim', array_keys(config('trello_sync.remake_label_actions.remove', [])))));
                $currentLabel = $remake->reason_label ?: $remake->label_name;
                $currentPoints = $remake->label_points ?? $remake->estimate_points;
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
                        <div class="text-gray-500">Remake reason label</div>
                        <div class="font-medium">{{ $remake->reason_label ?? '—' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                        <div class="text-gray-500">Remove label</div>
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
                        <h3 class="font-semibold">Edit remake data (local only)</h3>
                        <div class="text-xs text-gray-500">Changes only affect remake reporting in this app, not Trello.</div>
                    </div>
                    @if($remake->removed_at)
                        <span class="text-xs text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded">Removed {{ optional($remake->removed_at)->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
                <div class="mt-3 rounded border border-blue-100 bg-blue-50 text-blue-900 text-xs p-3">
                    You can set either a remake reason <strong>or</strong> a remove-from-consideration label, not both.
                    When a reason label is set, estimate points are locked.
                </div>
                @php($isRemoved = (bool) $remake->removed_at)
                <form method="post" action="{{ route('remakes.update', $remake) }}" class="mt-4 grid md:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Remake label</label>
                        <select name="remake_label" id="remakeLabelSelect" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" @disabled($isRemoved)>
                            <option value="">None</option>
                            @if(count($reasonOptions))
                                <optgroup label="Reason labels">
                                    @foreach($reasonOptions as $option)
                                        <option value="{{ $option }}" data-type="reason" @selected(old('remake_label', $currentLabel) === $option)>{{ $option }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if(count($labelOptions))
                                <optgroup label="Remove labels">
                                    @foreach($labelOptions as $option)
                                        <option value="{{ $option }}" data-type="remove" @selected(old('remake_label', $currentLabel) === $option)>{{ $option }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                        <div class="text-xs text-gray-500 mt-1">Select a reason or remove label.</div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-gray-300">Points</label>
                        <input type="number" name="points" id="remakePointsInput" value="{{ old('points', $currentPoints) }}"
                               class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" @disabled($isRemoved)
                               @if($isRemoved) data-removed="1" @endif />
                        <div class="text-xs text-gray-500 mt-1">Editable for remove labels. Read-only for reason labels.</div>
                    </div>

                    <div class="md:col-span-2">
                        <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm disabled:opacity-50 disabled:cursor-not-allowed" @disabled($isRemoved)>
                            Save local changes
                        </button>
                        @if($isRemoved)
                            <div class="text-xs text-gray-500 mt-2">Editing is disabled because this remake was removed in Trello.</div>
                        @endif
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold">Trello actions</h3>
                        <div class="text-xs text-gray-500">Applies changes directly on Trello.</div>
                    </div>
                    <form method="post" action="{{ route('remakes.refresh.show', $remake) }}">
                        @csrf
                        <button id="refreshRemakeBtn" class="px-3 py-1.5 rounded border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-900">
                            Refresh from Trello
                        </button>
                    </form>
                </div>

                @if($trelloActionsAllowed)
                    <form method="post" action="{{ route('remakes.trello.update', $remake) }}" class="grid md:grid-cols-2 gap-4">
                        @csrf
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 dark:text-gray-300">Card name</label>
                            <input name="trello_name" value="{{ old('trello_name', $trelloCard['name'] ?? '') }}"
                                   class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"/>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-300">Cover color</label>
                            <select name="trello_cover_color" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                @foreach(config('trello_sync.card_cover_colors', []) as $color)
                                    @php($selected = ($trelloCard['cover']['color'] ?? null) === $color)
                                    <option value="{{ $color }}" @selected($selected)>
                                        {{ $color }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-300">Cover size</label>
                            <select name="trello_cover_size" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                @foreach(config('trello_sync.card_cover_sizes', []) as $size)
                                    @php($selected = ($trelloCard['cover']['size'] ?? null) === $size)
                                    <option value="{{ $size }}" @selected($selected)>
                                        {{ ucfirst($size) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 dark:text-gray-300">Cover brightness</label>
                            <select name="trello_cover_brightness" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                @foreach(config('trello_sync.card_cover_brightness', []) as $brightness)
                                    @php($selected = ($trelloCard['cover']['brightness'] ?? null) === $brightness)
                                    <option value="{{ $brightness }}" @selected($selected)>
                                        {{ ucfirst($brightness) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <button class="px-4 py-2 rounded bg-blue-600 text-white text-sm">Update Trello card</button>
                        </div>
                    </form>
                @else
                    <div class="text-sm text-gray-500">Trello editing is restricted to approved users.</div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const btn = document.getElementById('refreshRemakeBtn');
                if (!btn) return;
                const form = btn.closest('form');
                if (!form) return;
                form.addEventListener('submit', () => {
                    btn.disabled = true;
                    btn.textContent = 'Refreshing...';
                });
            })();

            (function () {
                const labelSelect = document.getElementById('remakeLabelSelect');
                const pointsInput = document.getElementById('remakePointsInput');
                if (!labelSelect || !pointsInput) return;

                function syncLocks() {
                    const option = labelSelect.options[labelSelect.selectedIndex];
                    const type = option?.dataset?.type || '';
                    const isReason = type === 'reason';
                    pointsInput.readOnly = isReason;
                    pointsInput.disabled = pointsInput.hasAttribute('data-removed');
                }

                labelSelect.addEventListener('change', syncLocks);
                syncLocks();
            })();
        </script>
    @endpush
</x-layouts.app>
