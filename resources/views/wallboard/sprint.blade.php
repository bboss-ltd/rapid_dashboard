    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sprint Overview</title>
    @livewireStyles
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            margin: 0;
            background: #0b0f19;
            color: #e8eefc;
        }

        .wrap {
            padding: 32px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
        }

        .title {
            font-size: 40px;
            font-weight: 800;
            line-height: 1.1;
        }

        .sub {
            opacity: .8;
            margin-top: 8px;
            font-size: 16px;
        }

        .cardHeader {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .cardTitle {
            font-weight: 700;
            font-size: 16px;
        }

        .cardSub {
            font-size: 12px;
            opacity: .8;
            margin-top: 4px;
        }

        .cardAction {
            align-self: flex-start;
        }

        .headerAction {
            margin-left: 10px;
            border: 1px solid rgba(255, 255, 255, .15);
            background: transparent;
            color: #e8eefc;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            min-width: 110px;
            transition: background 120ms ease, border-color 120ms ease;
        }

        .headerAction--synced {
            background: rgba(101, 211, 138, .18);
            border-color: rgba(101, 211, 138, .4);
        }

        .sync-only {
            display: none;
        }

        body[data-syncing="1"] .sync-only {
            display: inline-block;
        }

        .headerMeta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }

        .headerMetaBadge {
            opacity: .85;
        }

        .row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }

        .topRow {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
            margin-bottom: 38px;
        }

        .topRow .chartCard {
            height: 100%;
        }

        .footerRow {
            display: grid;
            grid-template-columns: 1fr;
            margin-top: 16px;
        }

        .chartCard {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 18px;
            padding: 18px;
        }

        canvas {
            width: 100%;
            height: 360px;
        }

        .foot {
            margin-top: 16px;
            opacity: .7;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .10);
            font-size: 13px;
        }

        .warn {
            background: rgba(255, 180, 0, .14);
            border-color: rgba(255, 180, 0, .25);
        }

        .trendInline {
            display: flex;
            gap: 18px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .trendItem {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .trendValue {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .2px;
            margin: auto;
        }

        .machine.trendValue {
            margin-left: 0;
        }

        .trendMeta {
            font-size: 12px;
            opacity: .8;
            margin-top: 4px;
        }

        .trendArrow {
            font-size: 14px;
            margin-left: 6px;
        }

        .trendTop {
            font-size: 14px;
            opacity: .85;
            margin-top: 6px;
        }

        .trend-good {
            color: #65d38a;
        }

        .trend-bad {
            color: #ff6b6b;
        }

        .trend-neutral {
            color: #e8eefc;
        }
    </style>
</head>
<body>
<div class="wrap">
    <livewire:wallboard.header :sprint="$sprint" :refresh-seconds="$refreshSeconds" :debug="$debug" />

    <div class="row">
        <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="topRow">
    <livewire:wallboard.remakes-card :sprint="$sprint" :types="$types" :refresh-seconds="$refreshSeconds" :debug="$debug" :remakes-for="$remakesFor" />
    <livewire:wallboard.machines-card :sprint="$sprint" :refresh-seconds="$refreshSeconds" :debug="$debug" />
            </div>
            <livewire:wallboard.burndown-card :sprint="$sprint" :types="$types" :refresh-seconds="$refreshSeconds" :debug="$debug" />
            <livewire:wallboard.remake-reasons-by-line-card :sprint="$sprint" :refresh-seconds="$refreshSeconds" :debug="$debug" :remakes-for="$remakesFor" />
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
            <livewire:wallboard.remake-reasons-card :sprint="$sprint" :refresh-seconds="$refreshSeconds" :debug="$debug" :remakes-for="$remakesFor" />
            <livewire:wallboard.utilisation-card :sprint="$sprint" :refresh-seconds="$refreshSeconds" :debug="$debug" />
        </div>
    </div>
    <div class="footerRow">
        <div style="margin: auto">
            @php($revision = config('app.revision') ?: 'dev')
            @php($releasedAt = config('app.released_at'))
            <div class="badge headerMetaBadge">
                Rev: {{ \Illuminate\Support\Str::limit($revision, 12, '') }}
                @if($releasedAt)
                    <span style="margin-left:6px;">
                    Published: {{ \Illuminate\Support\Carbon::parse($releasedAt)->format('m-d H:i') }}
                </span>
                @endif
            </div>
        </div>
    </div>
</div>

@stack('scripts')
@livewireScripts

</body>
</html>
