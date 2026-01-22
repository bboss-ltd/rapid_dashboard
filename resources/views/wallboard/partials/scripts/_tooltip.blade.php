        // ========= Tooltip =========
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

        let latest = null;

        window.addEventListener('resize', () => {
            draw();
            drawRemakeReasons();
            if (latest) {
                drawDonut(Number(latest.done_points ?? 0), Number(latest.remaining_points ?? 0));
            } else {
                drawDonut(0, 0);
            }
        });

        if (tooltipEnabled) {
            canvas.addEventListener('mousemove', (evt) => {
                const idx = nearestPointIndex(evt);
                if (idx === null) return hideTooltip();
                showTooltipForIndex(idx, evt.clientX, evt.clientY);
            });
            canvas.addEventListener('mouseleave', hideTooltip);
        }
