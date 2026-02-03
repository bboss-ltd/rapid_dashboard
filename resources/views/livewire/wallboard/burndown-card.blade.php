@php
    $burndownPayload = [
        'series' => $series ?? [],
        'sprint' => [
            'id' => $sprint->id,
            'starts_at' => optional($sprint->starts_at)->toIso8601String(),
            'ends_at' => optional($sprint->ends_at)->toIso8601String(),
            'closed_at' => optional($sprint->closed_at)->toIso8601String(),
        ],
        'cfg' => $cfg ?? [],
    ];
@endphp
<div
    class="chartCard"
    wire:poll.{{ $refreshSeconds }}s
    data-wallboard-burndown='@json($burndownPayload, JSON_HEX_APOS | JSON_HEX_AMP)'
    data-wallboard-component-id="{{ $this->getId() }}"
>
    <div wire:loading wire:target="refreshFromManual" class="badge" style="position:absolute; margin-top:-30px; margin-left:6px; font-size:12px;">Refreshing…</div>
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Burndown</div>
            <div class="cardSub">Remaining progress over time</div>
        </div>
        @if($debug && $lastRenderedAt)
            <div class="badge cardAction">Updated {{ \Illuminate\Support\Carbon::parse($lastRenderedAt)->format('H:i:s') }}</div>
        @endif
        <div class="cardAction" style="text-align:right;">
            <div style="font-size:12px; opacity:.75;">Sprint progress</div>
            <div id="burndownProgressPct-{{ $this->getId() }}" style="font-size:20px; font-weight:700;">—%</div>
        </div>
    </div>
    <div style="margin-top: 10px;">
        <div id="chartWrap-{{ $this->getId() }}" style="position: relative;">
            <canvas id="burndown-{{ $this->getId() }}"></canvas>

            <div id="chartTooltip-{{ $this->getId() }}" style="
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

@push('scripts')
    <script>
        (function () {
            if (window.__wbBurndownInit) return;
            window.__wbBurndownInit = true;

            function drawBurndown(root) {
                const payloadRaw = root?.dataset?.wallboardBurndown;
                if (!payloadRaw) return;
                let payload = {};
                try {
                    payload = JSON.parse(payloadRaw);
                } catch (e) {
                    return;
                }

                const componentId = root.dataset.wallboardComponentId;
                const canvas = document.getElementById(`burndown-${componentId}`);
                const chartWrap = document.getElementById(`chartWrap-${componentId}`);
                const tooltip = document.getElementById(`chartTooltip-${componentId}`);
                const progressEl = document.getElementById(`burndownProgressPct-${componentId}`);
                if (!canvas) return;

                const snapshotSeries = payload.series || [];
                const cfg = payload.cfg || {};
                const sprint = payload.sprint || {};
                const display = cfg?.display ?? {};
                const displayMode = 'percent';
                const percentBasis = display.percent_basis ?? 'current_scope';
                const pctDecimals = Number(display.percent_decimals ?? 0);
                const workingDays = (cfg?.working_days ?? [1, 2, 3, 4, 5]);
                const useDaily = !!(cfg?.daily_series ?? true);

                const ctx = canvas.getContext('2d');
                const gridEnabled = !!(cfg?.grid?.enabled ?? true);
                const yTicks = Number(cfg?.grid?.y_ticks ?? 5);
                const xWeekLines = !!(cfg?.grid?.x_week_lines ?? true);
                const tooltipEnabled = !!(cfg?.tooltip?.enabled ?? true);

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
                    const js = d.getDay();
                    return js === 0 ? 7 : js;
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
                    return x.toISOString().slice(0, 10);
                }

                const snaps = (snapshotSeries ?? [])
                    .map(s => ({
                        taken_at: toDate(s.taken_at || s.takenAt || s.date || null),
                        type: s.type || s.snapshot_type || 'snapshot',
                        remaining_points: Number(s.remaining_points ?? 0),
                        done_points: Number(s.done_points ?? 0),
                        scope_points: Number(s.scope_points ?? 0),
                    }))
                    .filter(s => s.taken_at)
                    .sort((a, b) => a.taken_at - b.taken_at);

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
                        }
                    }

                    return ideal;
                }

                const actual = useDaily ? buildDailySeries() : buildSnapshotPointSeries();
                const idealRaw = buildIdealSeries(actual);
                const idealMap = new Map(idealRaw.map(p => [dayKey(p.date), p]));
                const ideal = actual.map(p => idealMap.get(dayKey(p.date)) ?? null);

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

                function clamp(n, min, max) {
                    return Math.max(min, Math.min(max, n));
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

                function sizeCanvas() {
                    const rect = canvas.getBoundingClientRect();
                    canvas.width = Math.floor(rect.width * devicePixelRatio);
                    canvas.height = Math.floor(rect.height * devicePixelRatio);
                    ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
                }

                let pad, w, h, maxY;
                let pointPixels = [];

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
                            if (isoDow(d) === 1) {
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

                function showTooltipForIndex(i, clientX, clientY) {
                    if (!tooltipEnabled || !tooltip) return;

                    const p = actual[i];
                    if (!p || p.remaining_points === null) return;
                    const idealP = ideal[i] ?? null;

                    const dateStr = fmtDate.format(p.date);
                    const snapStr = p.last_snapshot_at ? fmtDateTime.format(p.last_snapshot_at) : '—';

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

                    const deltaDisp = idealP
                        ? (() => {
                            const delta = (displayMode === 'percent')
                                ? (toIdealDisplayY(idealP) - toDisplayY(p))
                                : (Number(idealP.remaining_points ?? 0) - Number(p.remaining_points ?? 0));
                            const sign = delta > 0 ? '+' : '';
                            return displayMode === 'percent'
                                ? `${sign}${roundPct(delta)}%`
                                : `${sign}${Math.round(delta)}`;
                        })()
                        : '—';

                    const doneDisp = (displayMode === 'percent')
                        ? `${roundPct(safePct(Number(p.done_points ?? 0), denomForPoint))}%`
                        : `${p.done_points}`;

                    tooltip.innerHTML = `
                <div style="font-weight:700; font-size:13px; margin-bottom:6px;">${dateStr}</div>
                <div style="display:grid; grid-template-columns: 1fr auto; gap: 6px 12px;">
                    <div>Remaining</div><div style="font-weight:700;">${actualDisp}</div>
                    <div>Ideal</div><div style="font-weight:700;">${idealDisp}</div>
                    <div>Delta</div><div>${deltaDisp}</div>
                    <div>Done</div><div>${doneDisp}</div>
                    <div>Last snapshot</div><div>${snapStr}</div>
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

                const latest = [...actual].reverse().find(p => p && p.remaining_points !== null) || null;

                if (progressEl) {
                    const done = Number(latest?.done_points ?? 0);
                    const remaining = Number(latest?.remaining_points ?? 0);
                    const total = Math.max(0, done) + Math.max(0, remaining);
                    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
                    progressEl.textContent = `${pct}%`;
                }

                if (tooltipEnabled) {
                    if (!canvas.dataset.wbTooltipBound) {
                        canvas.addEventListener('mousemove', (evt) => {
                            const idx = nearestPointIndex(evt);
                            if (idx === null) return hideTooltip();
                            showTooltipForIndex(idx, evt.clientX, evt.clientY);
                        });
                        canvas.addEventListener('mouseleave', hideTooltip);
                        canvas.dataset.wbTooltipBound = '1';
                    }
                }

                draw();
            }

            function drawAll() {
                document.querySelectorAll('[data-wallboard-burndown]').forEach(drawBurndown);
            }

            document.addEventListener('livewire:init', () => {
                drawAll();
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('morph.updated', ({component}) => {
                        if (!component || component.name !== 'wallboard.burndown-card') return;
                        const root = document.querySelector(`[data-wallboard-component-id="${component.id}"][data-wallboard-burndown]`);
                        if (root) drawBurndown(root);
                    });
                }
            });

            window.addEventListener('resize', drawAll);
        })();
    </script>
@endpush
