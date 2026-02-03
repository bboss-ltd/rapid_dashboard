<div
    class="chartCard"
    wire:poll.{{ $refreshSeconds }}s
    data-wallboard-reasons-by-line='@json($reasonByLine ?? [])'
    data-wallboard-line-options='@json($lineOptions ?? [])'
    data-wallboard-component-id="{{ $this->getId() }}"
>
    <div wire:loading wire:target="refreshFromManual" class="badge" style="position:absolute; margin-top:-30px; margin-left:6px; font-size:12px;">Refreshing…</div>
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Remake reasons by line</div>
            <div class="cardSub">Daily breakdown by production line</div>
        </div>
        @if($debug && $lastRenderedAt)
            <div class="badge cardAction">Updated {{ \Illuminate\Support\Carbon::parse($lastRenderedAt)->format('H:i:s') }}</div>
        @endif
    </div>

    <div style="position: relative; margin-top: 12px;">
        <canvas id="remakeReasonByLineChart-{{ $this->getId() }}" style="width: 100%; height: 220px;"></canvas>
    </div>

    <div id="remakeReasonByLineLegend-{{ $this->getId() }}" style="margin-top: 10px; display:flex; flex-wrap:wrap; gap:8px 12px; font-size:12px; opacity:.85;"></div>
</div>

@push('scripts')
    <script>
        (function () {
            if (window.__wbRemakeReasonsByLineInit) return;
            window.__wbRemakeReasonsByLineInit = true;

            function trelloColorToHex(color) {
                const map = {
                    green: '#65d38a',
                    yellow: '#ffd86b',
                    orange: '#ffb74a',
                    red: '#ff6b6b',
                    purple: '#b38bff',
                    blue: '#7fb2ff',
                    sky: '#8dd3ff',
                    lime: '#c2f970',
                    pink: '#f5a9e1',
                    black: '#303642',
                    gray: '#a9b4c7',
                };
                return map[color] ?? null;
            }

            function drawChart(root) {
                const dataRaw = root?.dataset?.wallboardReasonsByLine;
                if (!dataRaw) return;
                let payload = {};
                try {
                    payload = JSON.parse(dataRaw);
                } catch (e) {
                    return;
                }

                const componentId = root.dataset.wallboardComponentId;
                const canvas = document.getElementById(`remakeReasonByLineChart-${componentId}`);
                const legend = document.getElementById(`remakeReasonByLineLegend-${componentId}`);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                const lines = Array.isArray(payload.lines) ? payload.lines : [];
                const reasons = Array.isArray(payload.reasons) ? payload.reasons : [];
                const counts = payload.counts || {};
                const colorsByLabel = payload.colors || {};
                const lineOptionsRaw = root?.dataset?.wallboardLineOptions;
                let lineOptions = [];
                if (lineOptionsRaw) {
                    try {
                        lineOptions = JSON.parse(lineOptionsRaw);
                    } catch (e) {
                        lineOptions = [];
                    }
                }

                const slots = Array.isArray(lineOptions) && lineOptions.length > 0
                    ? [...lineOptions, 'Unknown']
                    : lines;

                const entries = slots
                    .map((line) => {
                        const row = counts[line] || {};
                        const total = reasons.reduce((sum, r) => sum + Number(row[r] || 0), 0);
                        return { line, row, total };
                    });

                const rect = canvas.getBoundingClientRect();
                canvas.width = Math.floor(rect.width * devicePixelRatio);
                canvas.height = Math.floor(rect.height * devicePixelRatio);
                ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
                ctx.clearRect(0, 0, rect.width, rect.height);

                if (legend) {
                    legend.innerHTML = '';
                }

                const hasAnyData = entries.some((entry) => entry.total > 0);
                if (entries.length === 0 || (!hasAnyData && (!lineOptions || lineOptions.length === 0))) {
                    ctx.fillStyle = '#e8eefc';
                    ctx.font = '14px system-ui';
                    ctx.fillText('No remake reasons for this day yet.', 16, 24);
                    return;
                }

                const fallbackColors = [
                    '#7fb2ff',
                    '#65d38a',
                    '#ffb74a',
                    '#ff6b6b',
                    '#b38bff',
                    '#7ad6d6',
                    '#ffd86b',
                    '#c2f970',
                    '#a9c5ff',
                    '#f5a9e1',
                    '#f28f61',
                    '#8dd3ff',
                ];

                const maxTotal = entries.reduce((m, e) => Math.max(m, e.total), 1);
                const labelSpace = 26;
                const chartHeight = rect.height - labelSpace;
                const gap = 14;
                const slotCount = Math.max(1, entries.length);
                const barWidth = Math.max(24, (rect.width - gap * (slotCount - 1)) / slotCount);

                entries.forEach((entry, idx) => {
                    const x = idx * (barWidth + gap);
                    let y = chartHeight;

                    reasons.forEach((reason, rIdx) => {
                        const value = Number(entry.row[reason] || 0);
                        if (!value) return;
                        const h = (value / maxTotal) * (chartHeight - 6);
                        const trelloColor = trelloColorToHex(colorsByLabel[reason] || '');
                        const color = trelloColor || fallbackColors[rIdx % fallbackColors.length];
                        ctx.fillStyle = color;
                        ctx.fillRect(x, y - h, barWidth, h);
                        y -= h;
                    });

                    ctx.fillStyle = '#e8eefc';
                    ctx.font = '12px system-ui';
                    ctx.textAlign = 'center';
                    ctx.fillText(entry.line, x + barWidth / 2, rect.height - 8);
                });

                if (legend) {
                    const totalsByReason = {};
                    entries.forEach((entry) => {
                        reasons.forEach((reason) => {
                            totalsByReason[reason] = (totalsByReason[reason] || 0) + Number(entry.row[reason] || 0);
                        });
                    });

                    reasons
                        .filter((reason) => (totalsByReason[reason] || 0) > 0)
                        .forEach((reason, idx) => {
                            const item = document.createElement('div');
                            item.style.display = 'flex';
                            item.style.alignItems = 'center';
                            item.style.gap = '6px';
                            const swatch = document.createElement('span');
                            swatch.style.width = '10px';
                            swatch.style.height = '10px';
                            swatch.style.borderRadius = '999px';
                            const trelloColor = trelloColorToHex(colorsByLabel[reason] || '');
                            swatch.style.background = trelloColor || fallbackColors[idx % fallbackColors.length];
                            const text = document.createElement('span');
                            text.textContent = reason;
                            item.appendChild(swatch);
                            item.appendChild(text);
                            legend.appendChild(item);
                        });
                }
            }

            function drawAll() {
                document.querySelectorAll('[data-wallboard-reasons-by-line]').forEach(drawChart);
            }

            function bindLivewireHooks() {
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('morph.updated', ({ el }) => {
                        const root = el?.closest?.('[data-wallboard-reasons-by-line]');
                        if (root) drawChart(root);
                    });
                }
            }

            document.addEventListener('livewire:init', () => {
                drawAll();
                bindLivewireHooks();
            });

            window.addEventListener('resize', () => drawAll());
        })();
    </script>
@endpush
