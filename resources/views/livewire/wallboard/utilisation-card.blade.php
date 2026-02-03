<div
    class="chartCard"
    wire:poll.{{ $refreshSeconds }}s
    data-wallboard-utilisation='@json($utilisation ?? [])'
    data-wallboard-component-id="{{ $this->getId() }}"
>
    <div wire:loading wire:target="refreshFromManual" class="badge" style="position:absolute; margin-top:-30px; margin-left:6px; font-size:12px;">Refreshing…</div>
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Utilisation</div>
            @php($utilDays = (int) (config('wallboard.utilisation.summary_days') ?? 7))
            <div class="cardSub">Average (last {{ $utilDays }} working days)</div>
        </div>
        @if($debug && $lastRenderedAt)
            <div class="badge cardAction">Updated {{ \Illuminate\Support\Carbon::parse($lastRenderedAt)->format('H:i:s') }}</div>
        @endif
    </div>

    <div style="position: relative; margin-top: 12px;">
        <canvas id="utilisationDonut-{{ $this->getId() }}" style="width: 100%; height: 220px;"></canvas>
        <div id="utilisationLabel-{{ $this->getId() }}" style="
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    pointer-events:none;
">
            <div id="utilisationPct-{{ $this->getId() }}" style="font-size: 34px; font-weight: 800;">—%</div>
            <div style="font-size: 12px; opacity: .8;">utilisation</div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            if (window.__wbUtilisationInit) return;
            window.__wbUtilisationInit = true;

            function drawDonut(root) {
                const dataRaw = root?.dataset?.wallboardUtilisation;
                if (!dataRaw) return;
                let util = {};
                try {
                    util = JSON.parse(dataRaw);
                } catch (e) {
                    return;
                }

                const componentId = root.dataset.wallboardComponentId;
                const donut = document.getElementById(`utilisationDonut-${componentId}`);
                const pctEl = document.getElementById(`utilisationPct-${componentId}`);
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

                const pct = Number(util.total_percent ?? 0);
                const clamped = Math.max(0, Math.min(100, pct));
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

            function drawAll() {
                document.querySelectorAll('[data-wallboard-utilisation]').forEach(drawDonut);
            }

            document.addEventListener('livewire:init', () => {
                drawAll();
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('morph.updated', ({component}) => {
                        if (!component || component.name !== 'wallboard.utilisation-card') return;
                        const root = document.querySelector(`[data-wallboard-component-id="${component.id}"][data-wallboard-utilisation]`);
                        if (root) drawDonut(root);
                    });
                }
            });

            window.addEventListener('resize', drawAll);
        })();
    </script>
@endpush
