        // ========= Donut chart =========
        function drawDonut(done, remaining) {
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

            const total = Math.max(0, done) + Math.max(0, remaining);
            const pct = total > 0 ? (done / total) * 100 : 0;

            if (pctEl) pctEl.textContent = `${Math.round(pct)}%`;

            dctx.clearRect(0, 0, w, h);
            dctx.lineWidth = thickness;
            dctx.lineCap = 'round';

            // background ring
            dctx.beginPath();
            dctx.strokeStyle = 'rgba(148,163,184,0.30)';
            dctx.arc(cx, cy, radius, 0, Math.PI * 2);
            dctx.stroke();

            // done arc (start at top)
            const start = -Math.PI / 2;
            const end = start + (Math.PI * 2) * (total > 0 ? (done / total) : 0);

            dctx.beginPath();
            dctx.strokeStyle = 'rgba(127,178,255,0.95)';
            dctx.arc(cx, cy, radius, start, end);
            dctx.stroke();
        }
