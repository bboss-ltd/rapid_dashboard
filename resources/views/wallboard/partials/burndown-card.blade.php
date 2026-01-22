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

@push('scripts')
    <script>
        (function () {
            // Burndown chart renderer + tooltip handling.
            const W = window.Wallboard;
            if (!W) return;

            const canvas = document.getElementById('burndown');
            const chartWrap = document.getElementById('chartWrap');
            const tooltip = document.getElementById('chartTooltip');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const cfg = W.cfg ?? {};
            const gridEnabled = !!(cfg?.grid?.enabled ?? true);
            const yTicks = Number(cfg?.grid?.y_ticks ?? 5);
            const xWeekLines = !!(cfg?.grid?.x_week_lines ?? true);
            const tooltipEnabled = !!(cfg?.tooltip?.enabled ?? true);
            const {actual, ideal, displayMode, percentBasis, startScopeForBasis} = W;
            const {startOfDay, isoDow, safePct, roundPct} = W.helpers;

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
                        if (W.workingDays.includes(isoDow(d))) continue;
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
                // Draw axes, ideal line, actual line, and points.
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
                // Show tooltip for nearest point.
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
                // Find nearest plotted point for tooltip hit-testing.
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
            W.latestPoint = latest;

            if (tooltipEnabled) {
                canvas.addEventListener('mousemove', (evt) => {
                    const idx = nearestPointIndex(evt);
                    if (idx === null) return hideTooltip();
                    showTooltipForIndex(idx, evt.clientX, evt.clientY);
                });
                canvas.addEventListener('mouseleave', hideTooltip);
            }

            draw();
            W.onResize.push(draw);
        })();
    </script>
@endpush
