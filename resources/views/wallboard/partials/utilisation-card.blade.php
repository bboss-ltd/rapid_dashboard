<div class="chartCard">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Utilisation</div>
            @php($utilDays = (int) (config('wallboard.utilisation.summary_days') ?? 7))
            <div class="cardSub">Average (last {{ $utilDays }} working days)</div>
        </div>
    </div>

    <div style="position: relative; margin-top: 12px;">
        <canvas id="utilisationDonut" style="width: 100%; height: 220px;"></canvas>
        <div id="utilisationLabel" style="
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    pointer-events:none;
">
            <div id="utilisationPct" style="font-size: 34px; font-weight: 800;">—%</div>
            <div style="font-size: 12px; opacity: .8;">utilisation</div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            // Utilisation donut (last 7 working days).
            const W = window.Wallboard;
            if (!W) return;

            const donut = document.getElementById('utilisationDonut');
            const pctEl = document.getElementById('utilisationPct');
            if (!donut) return;

            function drawDonut(pct) {
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

                const clamped = Math.max(0, Math.min(100, pct ?? 0));
                if (pctEl) pctEl.textContent = `${Math.round(clamped)}%`;

                dctx.clearRect(0, 0, w, h);
                dctx.lineWidth = thickness;
                dctx.lineCap = 'round';

                dctx.beginPath();
                dctx.strokeStyle = 'rgba(148,163,184,0.30)';
                dctx.arc(cx, cy, radius, 0, Math.PI * 2);
                dctx.stroke();

                const start = -Math.PI / 2;
                const end = start + (Math.PI * 2) * (clamped / 100);

                dctx.beginPath();
                dctx.strokeStyle = 'rgba(127,178,255,0.95)';
                dctx.arc(cx, cy, radius, start, end);
                dctx.stroke();
            }

            function render() {
                const util = W.utilisation || {};
                const pct = Number(util.total_percent ?? 0);
                drawDonut(pct);
            }

            render();
            W.onResize.push(render);
        })();
    </script>
@endpush
