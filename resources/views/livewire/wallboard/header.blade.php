<div class="top" wire:poll.{{ $refreshSeconds }}s>
    <div>
        <div class="sub">
            <x-ui.datetime :value="$sprint->starts_at" :format="config('display.date')"/>
            →
            <x-ui.datetime :value="$sprint->ends_at" :format="config('display.date')"/>
            <button
                class="badge headerAction"
                wire:click="sync"
                wire:loading.attr="disabled"
                wire:target="sync"
            >
                <span wire:loading.remove wire:target="sync">Manual re-sync</span>
                <span wire:loading wire:target="sync">Syncing…</span>
            </button>
        </div>
    </div>

    <div class="headerMeta">
        <div class="badge">
            Last refresh:
            <x-ui.datetime :value="now()" :format="config('display.datetime_seconds')"/>
        </div>
        @if($statusMessage)
            <div class="badge headerMetaBadge">{{ $statusMessage }}</div>
        @endif
    </div>
</div>
