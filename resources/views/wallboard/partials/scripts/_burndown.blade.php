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
