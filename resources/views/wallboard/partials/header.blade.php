<div class="top">
    <div>
        <div class="title">Sprint Overview</div>
        <div class="sub">
            <x-ui.datetime :value="$sprint->starts_at" :format="config('display.date')"/>
            →
            <x-ui.datetime :value="$sprint->ends_at" :format="config('display.date')"/>
            @if($sprint->closed_at)
                <span class="badge warn" style="margin-left: 10px;">Closed</span>
            @else
                <span class="badge" style="margin-left: 10px;">Open</span>
            @endif
            <button id="manualSyncBtn"
                    class="badge"
                    style="margin-left: 10px; border:1px solid rgba(255,255,255,.15); background: transparent; color: #e8eefc; padding: 6px 10px; border-radius: 999px; font-size: 13px;">
                Manual re-sync
            </button>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
        <div class="badge">
            Last refresh:
            <x-ui.datetime :value="now()" :format="config('display.datetime_seconds')"/>
        </div>
        @php($revision = config('app.revision') ?: 'dev')
        @php($releasedAt = config('app.released_at'))
        <div class="badge" style="opacity:.85;">
            Rev: {{ \Illuminate\Support\Str::limit($revision, 12, '') }}
            @if($releasedAt)
                <span style="margin-left:6px;">
                    Published: {{ \Illuminate\Support\Carbon::parse($releasedAt)->format('m-d H:i') }}
                </span>
            @endif
        </div>
    </div>
</div>
