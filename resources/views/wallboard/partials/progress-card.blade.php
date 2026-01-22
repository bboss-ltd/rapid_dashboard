<div class="chartCard">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Sprint progress</div>
            <div class="cardSub">Completed vs Remaining</div>
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

@push('scripts')
    <script>
        (function () {
            // Sprint progress donut (done vs remaining).
            const W = window.Wallboard;
            if (!W) return;

            const donut = document.getElementById('progressDonut');
            const pctEl = document.getElementById('progressPct');
            if (!donut) return;

            function drawDonut(done, remaining) {
                // Canvas donut rendering.
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

                dctx.beginPath();
                dctx.strokeStyle = 'rgba(148,163,184,0.30)';
                dctx.arc(cx, cy, radius, 0, Math.PI * 2);
                dctx.stroke();

                const start = -Math.PI / 2;
                const end = start + (Math.PI * 2) * (total > 0 ? (done / total) : 0);

                dctx.beginPath();
                dctx.strokeStyle = 'rgba(127,178,255,0.95)';
                dctx.arc(cx, cy, radius, start, end);
                dctx.stroke();
            }

            function render() {
                // Render based on latest snapshot point.
                const latest = W.latestPoint ?? null;
                if (latest) {
                    drawDonut(Number(latest.done_points ?? 0), Number(latest.remaining_points ?? 0));
                } else {
                    drawDonut(0, 0);
                }
            }

            render();
            W.onResize.push(render);
        })();
    </script>
@endpush
