        function drawRemakeReasons() {
            if (!remakeChart) return;
            const rctx = remakeChart.getContext('2d');
            if (!rctx) return;

            const entries = Object.entries(remakeReasonStats || {})
                .map(([label, count]) => [String(label), Number(count || 0)])
                .filter(([, count]) => count > 0);

            const total = entries.reduce((sum, [, count]) => sum + count, 0);

            const rect = remakeChart.getBoundingClientRect();
            remakeChart.width = Math.floor(rect.width * devicePixelRatio);
            remakeChart.height = Math.floor(rect.height * devicePixelRatio);
            rctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);

            rctx.clearRect(0, 0, rect.width, rect.height);

            if (remakeLegend) {
                remakeLegend.innerHTML = '';
            }

            if (total <= 0 || entries.length === 0) {
                rctx.fillStyle = '#e8eefc';
                rctx.font = '14px system-ui';
                rctx.fillText('No remake reasons yet.', 16, 24);
                return;
            }

            const colors = [
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

            const cx = rect.width / 2;
            const cy = rect.height / 2;
            const radius = Math.min(rect.width, rect.height) / 2 - 6;
            const thickness = Math.max(14, radius * 0.28);
            const ringRadius = radius - (thickness / 2);

            let start = -Math.PI / 2;
            entries.forEach(([label, count], idx) => {
                const slice = (count / total) * Math.PI * 2;
                const end = start + slice;

                rctx.beginPath();
                rctx.strokeStyle = colors[idx % colors.length];
                rctx.lineWidth = thickness;
                rctx.lineCap = 'round';
                rctx.arc(cx, cy, ringRadius, start, end);
                rctx.stroke();

                if (remakeLegend) {
                    const item = document.createElement('div');
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.gap = '6px';
                    const swatch = document.createElement('span');
                    swatch.style.width = '10px';
                    swatch.style.height = '10px';
                    swatch.style.borderRadius = '999px';
                    swatch.style.background = colors[idx % colors.length];
                    const pct = Math.round((count / total) * 100);
                    const text = document.createElement('span');
                    text.textContent = `${label} ${pct}%`;
                    item.appendChild(swatch);
                    item.appendChild(text);
                    remakeLegend.appendChild(item);
                }

                start = end;
            });

            rctx.lineCap = 'butt';
        }
