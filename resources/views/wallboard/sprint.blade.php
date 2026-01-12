<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="{{ $refreshSeconds }}">
    <title>Wallboard — {{ $sprint->name }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #0b0f19; color: #e8eefc; }
        .wrap { padding: 32px; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .title { font-size: 40px; font-weight: 800; line-height: 1.1; }
        .sub { opacity: .8; margin-top: 8px; font-size: 16px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 24px; }
        .card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 18px; }
        .k { opacity: .75; font-size: 14px; }
        .v { font-size: 40px; font-weight: 800; margin-top: 8px; }
        .small { font-size: 22px; font-weight: 700; margin-top: 10px; opacity: .95; }
        .row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-top: 16px; }
        .chartCard { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 18px; }
        canvas { width: 100%; height: 360px; }
        .foot { margin-top: 16px; opacity: .7; font-size: 13px; display:flex; justify-content: space-between; }
        .badge { display:inline-block; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.10); font-size: 13px; }
        .warn { background: rgba(255, 180, 0, .14); border-color: rgba(255, 180, 0, .25); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <div class="title">{{ $sprint->name }}</div>
            <div class="sub">
                {{ optional($sprint->starts_at)->format('D j M H:i') }} → {{ optional($sprint->ends_at)->format('D j M H:i') }}
                @if($sprint->closed_at)
                    <span class="badge warn" style="margin-left: 10px;">Closed</span>
                @else
                    <span class="badge" style="margin-left: 10px;">Open</span>
                @endif
            </div>
        </div>

        <div class="badge">
            Last refresh: {{ now()->format('H:i:s') }}
        </div>
    </div>

    @php
        $endTotals = $summary['end_totals'] ?? null;
        $hasEnd = $summary['has_end_snapshot'] ?? false;

        // For “live” wallboard, use latest series point if available
        $liveScope = $latestPoint['scope_points'] ?? ($endTotals['scope_points'] ?? 0);
        $liveDone = $latestPoint['done_points'] ?? ($endTotals['completed_points'] ?? 0);
        $liveRemaining = $latestPoint['remaining_points'] ?? ($endTotals['remaining_points'] ?? 0);

        $rollover = $summary['rollover'] ?? ['cards_count' => 0, 'points' => 0];
    @endphp

    <div class="grid">
        <div class="card">
            <div class="k">Remaining points</div>
            <div class="v">{{ $liveRemaining }}</div>
        </div>

        <div class="card">
            <div class="k">Done points</div>
            <div class="v">{{ $liveDone }}</div>
        </div>

        <div class="card">
            <div class="k">Scope points</div>
            <div class="v">{{ $liveScope }}</div>
        </div>

        <div class="card">
            <div class="k">Rollover (if closed)</div>
            <div class="v">{{ $rollover['points'] ?? 0 }}</div>
            <div class="small">{{ $rollover['cards_count'] ?? 0 }} cards</div>
        </div>
    </div>

    <div class="row">
        <div class="chartCard">
            <div class="k">Burndown (Remaining points over time)</div>
            <div style="margin-top: 10px;">
                <canvas id="burndown"></canvas>
            </div>
            <div class="foot">
                <div>
                    Showing snapshot types:
                    <span class="badge">{{ implode(', ', request('types') ? explode(',', request('types')) : ['ad_hoc','end']) }}</span>
                </div>
                <div>
                    Points are based on snapshots (immutable history); reconciliation may add points when drift is detected.
                </div>
            </div>
        </div>

        <div class="chartCard">
            <div class="k">Notes</div>
            <div style="margin-top: 12px; line-height: 1.5; opacity: .9;">
                <div>• This page auto-refreshes every {{ $refreshSeconds }}s.</div>
                <div>• “Remaining” is the latest snapshot point.</div>
                <div>• Close the sprint in Trello to generate the end snapshot.</div>
            </div>

            <div style="margin-top: 18px;">
                <div class="k">Links</div>
                <div style="margin-top: 10px; display:flex; flex-direction:column; gap:8px;">
                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/burndown.json">Burndown JSON</a>
                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/burndown.csv">Burndown CSV</a>
                    <a style="color:#a9c5ff; text-decoration:none;" href="/reports/sprints/{{ $sprint->id }}/summary.json">Summary JSON</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const series = @json($series);

        const canvas = document.getElementById('burndown');
        const ctx = canvas.getContext('2d');

        function sizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.floor(rect.width * devicePixelRatio);
            canvas.height = Math.floor(rect.height * devicePixelRatio);
            ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
        }

        function draw() {
            sizeCanvas();

            const w = canvas.getBoundingClientRect().width;
            const h = canvas.getBoundingClientRect().height;

            ctx.clearRect(0, 0, w, h);

            if (!series || series.length < 1) {
                ctx.fillText('No snapshot data yet.', 16, 24);
                return;
            }

            const padding = { l: 42, r: 16, t: 16, b: 28 };

            const xs = series.map((_, i) => i);
            const ys = series.map(p => Number(p.remaining_points || 0));
            const maxY = Math.max(...ys, 1);
            const minY = 0;

            // axes
            ctx.globalAlpha = 0.5;
            ctx.beginPath();
            ctx.moveTo(padding.l, padding.t);
            ctx.lineTo(padding.l, h - padding.b);
            ctx.lineTo(w - padding.r, h - padding.b);
            ctx.strokeStyle = '#cfe0ff';
            ctx.stroke();
            ctx.globalAlpha = 1;

            // y labels (0 and max)
            ctx.fillStyle = '#e8eefc';
            ctx.font = '12px system-ui';
            ctx.fillText(String(maxY), 8, padding.t + 12);
            ctx.fillText('0', 16, h - padding.b + 4);

            function xScale(i) {
                if (series.length === 1) return padding.l;
                const span = (w - padding.l - padding.r);
                return padding.l + (i / (series.length - 1)) * span;
            }

            function yScale(v) {
                const span = (h - padding.t - padding.b);
                return padding.t + (1 - ((v - minY) / (maxY - minY))) * span;
            }

            // line
            ctx.beginPath();
            series.forEach((p, i) => {
                const x = xScale(i);
                const y = yScale(Number(p.remaining_points || 0));
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.strokeStyle = '#7fb2ff';
            ctx.lineWidth = 3;
            ctx.stroke();

            // points
            ctx.fillStyle = '#e8eefc';
            series.forEach((p, i) => {
                const x = xScale(i);
                const y = yScale(Number(p.remaining_points || 0));
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fill();
            });
        }

        window.addEventListener('resize', draw);
        draw();
    })();
</script>
</body>
</html>
