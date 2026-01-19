@php use Illuminate\Support\Str; @endphp
    <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="{{ $refreshSeconds }}">
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

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 24px;
        }

        .card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 18px;
            padding: 18px;
        }

        .k {
            opacity: .75;
            font-size: 14px;
        }

        .v {
            font-size: 40px;
            font-weight: 800;
            margin-top: 8px;
        }

        .small {
            font-size: 22px;
            font-weight: 700;
            margin-top: 10px;
            opacity: .95;
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

        .row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
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
            </div>
        </div>

        <div class="badge">
            Last refresh:
            <x-ui.datetime :value="now()" :format="config('display.datetime_seconds')"/>
        </div>
    </div>

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
            <div class="chartCard">
                <div class="cardHeader">
                    <div>
                        <div class="cardTitle">Remakes</div>
                        <div class="cardSub">Cards in Remakes list</div>
                    </div>
                </div>
                <div
                    style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top: 8px;">
                    <div class="v">{{ $remakeTotal ?? 0 }}</div>
                    @php
                        $trendToday = $remakeStats['trend_today'] ?? 'neutral';
                        $trendSprint = $remakeStats['trend_sprint'] ?? 'neutral';
                        $trendMonth = $remakeStats['trend_month'] ?? 'neutral';
                        $trendIcon = fn($t) => $t === 'bad' ? '▲' : ($t === 'good' ? '▼' : '—');
                    @endphp
                    <div class="trendInline" style="justify-content:flex-end;">
                        <div class="trendItem">
                            <div class="trendTop">Sprint to date: {{ $remakeStats['sprint'] ?? 0 }}</div>
                        </div>
                        <div class="trendItem">
                            <div class="trendValue trend-{{ $trendToday }}">{{ $remakeStats['today'] ?? 0 }}<span class="trendArrow">{{ $trendIcon($trendToday) }}</span></div>
                            <div class="trendMeta">Today vs yesterday</div>
                        </div>
                        <div class="trendItem">
                            <div class="trendValue trend-{{ $trendSprint }}">{{ $remakeStats['sprint'] ?? 0 }}<span class="trendArrow">{{ $trendIcon($trendSprint) }}</span></div>
                            <div class="trendMeta">Sprint pace</div>
                        </div>
                        <div class="trendItem">
                            <div class="trendValue trend-{{ $trendMonth }}">{{ $remakeStats['month'] ?? 0 }}<span class="trendArrow">{{ $trendIcon($trendMonth) }}</span></div>
                            <div class="trendMeta">Month pace</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chartCard">
                <div class="cardHeader">
                    <div>
                        <div class="cardTitle">Burndown</div>
                        <div class="cardSub">Remaining progress over time</div>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <div id="chartWrap" style="position: relative;">
                        <canvas id="burndown"></canvas>

                        <div id="chartTooltip" style="
        position:absolute;
        display:none;
        pointer-events:none;
        max-width: 320px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.12);
        background: rgba(10,14,24,.92);
        color: #e8eefc;
        font-size: 12px;
        line-height: 1.35;
        box-shadow: 0 12px 30px rgba(0,0,0,.35);
        backdrop-filter: blur(6px);
    "></div>
                    </div>

                </div>
                <div class="foot">
                    <div>
                        Based on snapshots; historical view stays consistent over time.
                    </div>
                </div>
            </div>
        </div>


        <div class="chartCard">
            {{--            <div class="k">Notes</div>--}}
            {{--            <div style="margin-top: 12px; line-height: 1.5; opacity: .9;">--}}
            {{--                <div>• This page auto-refreshes every {{ $refreshSeconds }}s.</div>--}}
            {{--                <div>• “Remaining” is the latest snapshot point.</div>--}}
            {{--                <div>• Close the sprint in Trello to generate the end snapshot.</div>--}}
            {{--            </div>--}}

            {{--            <div style="margin-top: 18px;">--}}
            {{--                <div class="k">Links</div>--}}
            {{--                <div style="margin-top: 10px; display:flex; flex-direction:column; gap:8px;">--}}
            {{--                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/burndown.json">Burndown JSON</a>--}}
            {{--                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/burndown.csv">Burndown CSV</a>--}}
            {{--                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/summary.json">Summary JSON</a>--}}
            {{--                </div>--}}
            {{--            </div>--}}

            <div class="chartCard">
                <div class="cardHeader">
                    <div>
                        <div class="cardTitle">Sprint progress</div>
                        <div class="cardSub">Completed vs Remaining</div>
                    </div>
                    <div class="cardAction">
                        <button id="manualSyncBtn"
                                style="border:1px solid rgba(255,255,255,.15); background: transparent; color: #e8eefc; padding: 6px 10px; border-radius: 10px; font-size: 12px;">
                            Manual re-sync
                        </button>
                    </div>
                </div>

                <div style="position: relative; margin-top: 12px;">
                    <canvas id="progressDonut" style="width: 100%; height: 220px;"></canvas>
                    <div id="progressLabel" style="
            position:absolute;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-direction:column;
            pointer-events:none;
        ">
                        <div id="progressPct" style="font-size: 34px; font-weight: 800;">—%</div>
                        <div style="font-size: 12px; opacity: .8;">complete</div>
                    </div>
                </div>

                <div id="syncStatus" style="margin-top: 8px; font-size: 12px; opacity: .8;"></div>
            </div>

        </div>


    </div>

    @if($sprint->sprint_goal)
        <div class="sub" style="margin-top:16px;">
            Goal: {{ Str::of($sprint->sprint_goal)->squish()->limit(220) }}
        </div>
    @endif

</div>

<script>
    (function () {
        // ========= Inputs from Laravel =========
        const snapshotSeries = @json($series ?? []);
        const sprint = {
            id: @json($sprint->id),
            starts_at: @json(optional($sprint->starts_at)?->toIso8601String()),
            ends_at: @json(optional($sprint->ends_at)?->toIso8601String()),
            closed_at: @json(optional($sprint->closed_at)?->toIso8601String()),
        };

        const cfg = @json(config('wallboard.burndown', []));
        const display = cfg?.display ?? {};
        const displayMode = 'percent'; // force percent to avoid showing raw story points
        const percentBasis = display.percent_basis ?? 'current_scope'; // 'current_scope' | 'start_scope'
        const showRaw = false;
        const pctDecimals = Number(display.percent_decimals ?? 0);

        const workingDays = (cfg?.working_days ?? [1, 2, 3, 4, 5]); // ISO 1..7
        const gridEnabled = !!(cfg?.grid?.enabled ?? true);
        const yTicks = Number(cfg?.grid?.y_ticks ?? 5);
        const xWeekLines = !!(cfg?.grid?.x_week_lines ?? true);
        const tooltipEnabled = !!(cfg?.tooltip?.enabled ?? true);
        const useDaily = !!(cfg?.daily_series ?? true);

        // ========= DOM =========
        const canvas = document.getElementById('burndown');
        const chartWrap = document.getElementById('chartWrap');
        const tooltip = document.getElementById('chartTooltip');
        const donut = document.getElementById('progressDonut');
        const pctEl = document.getElementById('progressPct');
        const syncBtn = document.getElementById('manualSyncBtn');
        const syncStatus = document.getElementById('syncStatus');

        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        // ========= Formatting helpers =========
        const fmtDate = new Intl.DateTimeFormat('en-GB', {year: 'numeric', month: '2-digit', day: '2-digit'});
        const fmtDateTime = new Intl.DateTimeFormat('en-GB', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const fmtDay = new Intl.DateTimeFormat('en-GB', {weekday: 'short', day: '2-digit', month: '2-digit'});

        function toDate(x) {
            if (!x) return null;
            const d = new Date(x);
            return isNaN(d.getTime()) ? null : d;
        }

        function startOfDay(d) {
            const x = new Date(d);
            x.setHours(0, 0, 0, 0);
            return x;
        }

        function endOfDay(d) {
            const x = new Date(d);
            x.setHours(23, 59, 59, 999);
            return x;
        }

        function isoDow(d) {
            // JS: 0=Sun..6=Sat => ISO: 1=Mon..7=Sun
            const js = d.getDay();
            return js === 0 ? 7 : js;
        }

        function clamp(n, min, max) {
            return Math.max(min, Math.min(max, n));
        }

        function safePct(numer, denom) {
            if (!denom || denom <= 0) return 0;
            return (numer / denom) * 100;
        }

        function roundPct(v) {
            const p = Math.pow(10, pctDecimals);
            return Math.round(v * p) / p;
        }

        function dayKey(d) {
            const x = startOfDay(d);
            return x.toISOString().slice(0, 10); // YYYY-MM-DD
        }

        // ========= Normalize snapshots =========
        const snaps = (snapshotSeries ?? [])
            .map(s => ({
                taken_at: toDate(s.taken_at || s.takenAt || s.date || null),
                type: s.type || s.snapshot_type || 'snapshot',
                remaining_points: Number(s.remaining_points ?? 0),
                done_points: Number(s.done_points ?? 0),
                scope_points: Number(s.scope_points ?? 0),
                raw: s,
            }))
            .filter(s => s.taken_at)
            .sort((a, b) => a.taken_at - b.taken_at);

        // ========= Build series =========
        function buildDailySeries() {
            if (!snaps.length) return [];

            const sprintStart = startOfDay(toDate(sprint.starts_at) ?? snaps[0].taken_at);
            const sprintEndBase = toDate(sprint.ends_at) ?? snaps[snaps.length - 1].taken_at;
            const sprintEnd = startOfDay(sprintEndBase);

            const today = startOfDay(new Date());
            const endDay = (toDate(sprint.closed_at) ? sprintEnd : (today < sprintEnd ? today : sprintEnd));

            const days = [];
            for (let d = new Date(sprintStart); d <= sprintEnd; d.setDate(d.getDate() + 1)) {
                days.push(new Date(d));
            }

            let cursor = 0;
            let last = null;
            const daily = [];

            for (const day of days) {
                const cutoff = endOfDay(day);
                while (cursor < snaps.length && snaps[cursor].taken_at <= cutoff) {
                    last = snaps[cursor];
                    cursor++;
                }

                const isFuture = day > endDay;
                if (last) {
                    daily.push({
                        date: new Date(day),
                        remaining_points: isFuture ? null : last.remaining_points,
                        done_points: isFuture ? null : last.done_points,
                        scope_points: last.scope_points,
                        last_snapshot_at: last.taken_at,
                        last_snapshot_type: last.type,
                        is_future: isFuture,
                    });
                } else {
                    daily.push({
                        date: new Date(day),
                        remaining_points: null,
                        done_points: null,
                        scope_points: 0,
                        last_snapshot_at: null,
                        last_snapshot_type: null,
                        is_future: isFuture,
                    });
                }
            }

            return daily;
        }

        function buildSnapshotPointSeries() {
            return snaps.map(s => ({
                date: s.taken_at,
                remaining_points: s.remaining_points,
                done_points: s.done_points,
                scope_points: s.scope_points,
                last_snapshot_at: s.taken_at,
                last_snapshot_type: s.type,
            }));
        }

        function buildIdealSeries(actual) {
            if (!actual.length) return [];

            const startDate = startOfDay(actual[0].date);
            const endDate = startOfDay(actual[actual.length - 1].date);

            // start scope = first non-zero, else max seen
            let startScope = 0;
            for (const p of actual) {
                const s = Number(p.scope_points ?? 0);
                if (s > 0) {
                    startScope = s;
                    break;
                }
            }
            if (startScope <= 0) {
                startScope = Math.max(...actual.map(p => Number(p.scope_points ?? 0)), 0);
            }
            if (startScope <= 0) return [];

            // count working days inclusive
            let workingCount = 0;
            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                if (workingDays.includes(isoDow(d))) workingCount++;
            }
            if (workingCount <= 0) workingCount = 1;

            const burnPerWorkDay = startScope / workingCount;

            let remaining = startScope;
            const ideal = [];
            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                const day = new Date(d);
                ideal.push({
                    date: new Date(day),
                    remaining_points: Math.max(0, remaining),
                    scope_points: startScope,
                });

                if (workingDays.includes(isoDow(day))) {
                    remaining -= burnPerWorkDay;
                } // else plateau
            }

            return ideal;
        }

        const actual = useDaily ? buildDailySeries() : buildSnapshotPointSeries();
        const idealRaw = buildIdealSeries(actual);

        // Align ideal to actual by day-key (fixes "ideal line missing" issues)
        const idealMap = new Map(idealRaw.map(p => [dayKey(p.date), p]));
        const ideal = actual.map(p => idealMap.get(dayKey(p.date)) ?? null);

        // choose start scope for percent basis
        let startScopeForBasis = 0;
        for (const p of actual) {
            const s = Number(p.scope_points ?? 0);
            if (s > 0) {
                startScopeForBasis = s;
                break;
            }
        }
        if (startScopeForBasis <= 0) {
            startScopeForBasis = Math.max(...actual.map(p => Number(p.scope_points ?? 0)), 0);
        }

        function toDisplayY(point) {
            if (displayMode === 'points') return Number(point.remaining_points ?? 0);

            const denom =
                percentBasis === 'start_scope'
                    ? (startScopeForBasis || 0)
                    : Number(point.scope_points ?? 0);

            return roundPct(safePct(Number(point.remaining_points ?? 0), denom));
        }

        function toIdealDisplayY(point) {
            if (!point) return null;
            if (displayMode === 'points') return Number(point.remaining_points ?? 0);

            const denom =
                percentBasis === 'start_scope'
                    ? (startScopeForBasis || Number(point.scope_points ?? 0))
                    : Number(point.scope_points ?? startScopeForBasis ?? 0);

            return roundPct(safePct(Number(point.remaining_points ?? 0), denom));
        }

        // ========= Canvas drawing =========
        function sizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.floor(rect.width * devicePixelRatio);
            canvas.height = Math.floor(rect.height * devicePixelRatio);
            ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
        }

        let pad, w, h, maxY;
        let pointPixels = []; // for hover

        function xScale(i) {
            if (actual.length === 1) return pad.l;
            const span = (w - pad.l - pad.r);
            return pad.l + (i / (actual.length - 1)) * span;
        }

        function yScale(v) {
            const span = (h - pad.t - pad.b);
            return pad.t + (1 - (v / maxY)) * span;
        }

        function drawGrid() {
            if (!gridEnabled) return;

            if (actual.length > 1) {
                ctx.save();
                ctx.fillStyle = 'rgba(120, 170, 255, 0.10)';
                for (let i = 0; i < actual.length; i++) {
                    const d = startOfDay(actual[i].date);
                    if (workingDays.includes(isoDow(d))) continue;
                    const x1 = xScale(i);
                    const x2 = xScale(Math.min(i + 1, actual.length - 1));
                    const width = Math.max(1, x2 - x1);
                    ctx.fillRect(x1, pad.t, width, h - pad.t - pad.b);
                }
                ctx.restore();
            }

            ctx.save();
            ctx.globalAlpha = 0.25;
            ctx.strokeStyle = '#cfe0ff';
            ctx.lineWidth = 1;

            const ticks = Math.max(2, yTicks);
            for (let i = 0; i <= ticks; i++) {
                const y = pad.t + (i / ticks) * (h - pad.t - pad.b);
                ctx.beginPath();
                ctx.moveTo(pad.l, y);
                ctx.lineTo(w - pad.r, y);
                ctx.stroke();
            }

            if (actual.length > 2) {
                ctx.save();
                ctx.globalAlpha = 0.12;
                for (let i = 0; i < actual.length; i++) {
                    const x = xScale(i);
                    ctx.beginPath();
                    ctx.moveTo(x, pad.t);
                    ctx.lineTo(x, h - pad.b);
                    ctx.stroke();
                }
                ctx.restore();
            }

            if (xWeekLines && actual.length > 2) {
                for (let i = 0; i < actual.length; i++) {
                    const d = startOfDay(actual[i].date);
                    if (isoDow(d) === 1) { // Monday
                        const x = xScale(i);
                        ctx.beginPath();
                        ctx.moveTo(x, pad.t);
                        ctx.lineTo(x, h - pad.b);
                        ctx.stroke();
                    }
                }
            }

            ctx.restore();
        }

        function draw() {
            sizeCanvas();
            w = canvas.getBoundingClientRect().width;
            h = canvas.getBoundingClientRect().height;

            ctx.clearRect(0, 0, w, h);

            if (!actual.length) {
                ctx.fillStyle = '#e8eefc';
                ctx.font = '14px system-ui';
                ctx.fillText('No snapshot data yet.', 16, 24);
                return;
            }

            pad = {l: 50, r: 16, t: 16, b: 44};

            const actualYs = actual.filter(p => p.remaining_points !== null).map(p => toDisplayY(p));
            const idealYs = ideal.map(p => (p ? toIdealDisplayY(p) : null)).filter(v => v !== null);

            const rawMax = Math.max(...actualYs, ...(idealYs.length ? idealYs : [0]), 1);
            maxY = (displayMode === 'percent') ? 100 : Math.ceil(rawMax * 1.1);

            drawGrid();

            // Axes
            ctx.save();
            ctx.globalAlpha = 0.55;
            ctx.strokeStyle = '#cfe0ff';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(pad.l, pad.t);
            ctx.lineTo(pad.l, h - pad.b);
            ctx.lineTo(w - pad.r, h - pad.b);
            ctx.stroke();
            ctx.restore();

            // Y labels
            ctx.fillStyle = '#e8eefc';
            ctx.font = '12px system-ui';
            ctx.globalAlpha = 0.85;

            if (displayMode === 'percent') {
                ctx.fillText('100%', 8, pad.t + 12);
                ctx.fillText('0%', 16, h - pad.b + 4);
            } else {
                ctx.fillText(String(maxY), 8, pad.t + 12);
                ctx.fillText('0', 16, h - pad.b + 4);
            }
            ctx.globalAlpha = 1;

            // Ideal line (dashed)
            const hasAnyIdeal = ideal.some(p => p !== null);
            if (hasAnyIdeal) {
                ctx.save();
                ctx.strokeStyle = '#a9c5ff';
                ctx.globalAlpha = 0.55;
                ctx.lineWidth = 2;
                ctx.setLineDash([8, 6]);
                ctx.beginPath();

                let started = false;
                ideal.forEach((p, i) => {
                    if (!p) return;
                    const x = xScale(i);
                    const y = yScale(toIdealDisplayY(p));
                    if (!started) {
                        ctx.moveTo(x, y);
                        started = true;
                    } else {
                        ctx.lineTo(x, y);
                    }
                });

                ctx.stroke();
                ctx.restore();
            }

            // Actual line (only through current progress)
            ctx.save();
            ctx.strokeStyle = '#7fb2ff';
            ctx.lineWidth = 3;
            ctx.beginPath();
            let started = false;
            actual.forEach((p, i) => {
                if (p.remaining_points === null) return;
                const x = xScale(i);
                const y = yScale(toDisplayY(p));
                if (!started) {
                    ctx.moveTo(x, y);
                    started = true;
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.stroke();
            ctx.restore();

            // Points
            pointPixels = [];
            ctx.fillStyle = '#e8eefc';
            actual.forEach((p, i) => {
                if (p.remaining_points === null) return;
                const x = xScale(i);
                const y = yScale(toDisplayY(p));
                pointPixels.push({x, y, idx: i});

                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fill();
            });

            // X-axis day labels
            if (actual.length > 1) {
                ctx.save();
                ctx.fillStyle = 'rgba(232, 238, 252, 0.7)';
                ctx.font = '11px system-ui';
                const skip = actual.length > 24 ? 3 : (actual.length > 12 ? 2 : 1);
                for (let i = 0; i < actual.length; i += skip) {
                    const x = xScale(i);
                    const label = fmtDay.format(actual[i].date);
                    ctx.fillText(label, x - 18, h - 8);
                }
                ctx.restore();
            }
        }

        // ========= Tooltip =========
        function showTooltipForIndex(i, clientX, clientY) {
            if (!tooltipEnabled || !tooltip) return;

            const p = actual[i];
            if (!p || p.remaining_points === null) return;
            const idealP = ideal[i] ?? null;

            const dateStr = fmtDate.format(p.date);
            const snapStr = p.last_snapshot_at ? fmtDateTime.format(p.last_snapshot_at) : '—';
            const typeStr = p.last_snapshot_type ?? '—';

            const denomForPoint = (displayMode === 'percent')
                ? (percentBasis === 'start_scope'
                    ? (startScopeForBasis || 0)
                    : Number(p.scope_points ?? 0))
                : 0;

            const actualDisp = (displayMode === 'percent')
                ? `${toDisplayY(p)}%`
                : `${p.remaining_points}`;

            const idealDisp = idealP
                ? (displayMode === 'percent'
                    ? `${toIdealDisplayY(idealP)}%`
                    : `${Math.round(idealP.remaining_points)}`)
                : '—';

            const doneDisp = (displayMode === 'percent')
                ? `${roundPct(safePct(Number(p.done_points ?? 0), denomForPoint))}%`
                : `${p.done_points}`;

            const scopeDisp = (displayMode === 'percent')
                ? '100%'
                : `${p.scope_points}`;

            tooltip.innerHTML = `
            <div style="font-weight:700; font-size:13px; margin-bottom:6px;">${dateStr}</div>
            <div style="display:grid; grid-template-columns: 1fr auto; gap: 6px 12px;">
                <div>Remaining</div><div style="font-weight:700;">${actualDisp}</div>
                <div>Ideal</div><div style="font-weight:700;">${idealDisp}</div>
                <div>Done</div><div>${doneDisp}</div>
                <div>Scope</div><div>${scopeDisp}</div>
                <div>Last snapshot</div><div>${snapStr}</div>
                <div>Type</div><div>${typeStr}</div>
            </div>
        `;

            tooltip.style.display = 'block';

            const wrapRect = chartWrap.getBoundingClientRect();
            const ttRect = tooltip.getBoundingClientRect();

            const x = clamp(clientX - wrapRect.left + 14, 8, wrapRect.width - ttRect.width - 8);
            const y = clamp(clientY - wrapRect.top + 14, 8, wrapRect.height - ttRect.height - 8);

            tooltip.style.left = x + 'px';
            tooltip.style.top = y + 'px';
        }

        function hideTooltip() {
            if (tooltip) tooltip.style.display = 'none';
        }

        function nearestPointIndex(evt) {
            const rect = canvas.getBoundingClientRect();
            const x = evt.clientX - rect.left;
            const y = evt.clientY - rect.top;

            let best = null;
            let bestDist = Infinity;

            for (const p of pointPixels) {
                const dx = p.x - x;
                const dy = p.y - y;
                const d = Math.sqrt(dx * dx + dy * dy);
                if (d < bestDist) {
                    bestDist = d;
                    best = p;
                }
            }

            if (!best || bestDist > 18) return null;
            return best.idx;
        }

        window.addEventListener('resize', draw);

        if (tooltipEnabled) {
            canvas.addEventListener('mousemove', (evt) => {
                const idx = nearestPointIndex(evt);
                if (idx === null) return hideTooltip();
                showTooltipForIndex(idx, evt.clientX, evt.clientY);
            });
            canvas.addEventListener('mouseleave', hideTooltip);
        }

        // ========= Donut chart =========
        function drawDonut(done, remaining) {
            if (!donut) return;
            const dctx = donut.getContext('2d');

            const rect = donut.getBoundingClientRect();
            donut.width = Math.floor(rect.width * devicePixelRatio);
            donut.height = Math.floor(rect.height * devicePixelRatio);
            dctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);

            const w = rect.width;
            const h = rect.height;
            const cx = w / 2;
            const cy = h / 2;
            const radius = Math.min(w, h) * 0.38;
            const thickness = Math.max(14, radius * 0.28);

            const total = Math.max(0, done) + Math.max(0, remaining);
            const pct = total > 0 ? (done / total) * 100 : 0;

            if (pctEl) pctEl.textContent = `${Math.round(pct)}%`;

            dctx.clearRect(0, 0, w, h);
            dctx.lineWidth = thickness;
            dctx.lineCap = 'round';

            // background ring
            dctx.beginPath();
            dctx.strokeStyle = 'rgba(148,163,184,0.30)';
            dctx.arc(cx, cy, radius, 0, Math.PI * 2);
            dctx.stroke();

            // done arc (start at top)
            const start = -Math.PI / 2;
            const end = start + (Math.PI * 2) * (total > 0 ? (done / total) : 0);

            dctx.beginPath();
            dctx.strokeStyle = 'rgba(127,178,255,0.95)';
            dctx.arc(cx, cy, radius, start, end);
            dctx.stroke();
        }

        // use latest point
        const latest = [...actual].reverse().find(p => p && p.remaining_points !== null) || null;
        if (latest) {
            drawDonut(Number(latest.done_points ?? 0), Number(latest.remaining_points ?? 0));
        } else {
            drawDonut(0, 0);
        }

        // ========= Manual re-sync =========
        if (syncBtn) {
            syncBtn.addEventListener('click', async () => {
                try {
                    syncBtn.disabled = true;
                    syncBtn.textContent = 'Syncing…';
                    if (syncStatus) syncStatus.textContent = 'Requesting sync…';

                    const res = await fetch(@json(route('wallboard.sprint.sync', $sprint)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json?.message || `HTTP ${res.status}`);

                    if (syncStatus) syncStatus.textContent = json?.message || 'Sync complete.';
                    syncBtn.textContent = 'Manual re-sync';

                    // refresh so charts update
                    setTimeout(() => location.reload(), 700);
                } catch (e) {
                    if (syncStatus) syncStatus.textContent = `Sync failed: ${e.message}`;
                    syncBtn.textContent = 'Manual re-sync';
                } finally {
                    syncBtn.disabled = false;
                }
            });
        }

        // ========= Initial draw =========
        draw();
    })();
</script>


</body>
</html>
