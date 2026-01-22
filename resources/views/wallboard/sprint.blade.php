    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sprint Overview</title>
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
    @include('wallboard.partials.header')

    @php

        $endTotals = $summary['end_totals'] ?? null;
        $hasEnd = $summary['has_end_snapshot'] ?? false;
        $latestPointData = $latestPoint ?? [];
        $rollover = $summary['rollover'] ?? ['cards_count' => 0, 'points' => 0];
        $liveRemakes = (int) ($latestPointData['remakes_count'] ?? 0);
        $remakeStats = $remakeStats ?? ['total' => null, 'today' => 0, 'sprint' => 0, 'month' => 0];
        $remakeTotal = $remakeStats['total'] ?? $liveRemakes;
    @endphp

    <div class="row">
        <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="topRow">
                @include('wallboard.partials.remakes-card')
                @include('wallboard.partials.machines-card')
            </div>
            @include('wallboard.partials.burndown-card')
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
            @include('wallboard.partials.remake-reasons-card')
            @include('wallboard.partials.progress-card')
        </div>
    </div>

</div>

@stack('scripts')

</body>
</html>
