        // ========= Formatting helpers =========
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

        function toDate(x) {
            if (!x) return null;
            const d = new Date(x);
            return isNaN(d.getTime()) ? null : d;
        }

        function startOfDay(d) {
            const x = new Date(d);
            x.setHours(0, 0, 0, 0);
            return x;
        }

        function endOfDay(d) {
            const x = new Date(d);
            x.setHours(23, 59, 59, 999);
            return x;
        }

        function isoDow(d) {
            // JS: 0=Sun..6=Sat => ISO: 1=Mon..7=Sun
            const js = d.getDay();
            return js === 0 ? 7 : js;
        }

        function clamp(n, min, max) {
            return Math.max(min, Math.min(max, n));
        }

        function safePct(numer, denom) {
            if (!denom || denom <= 0) return 0;
            return (numer / denom) * 100;
        }

        function roundPct(v) {
            const p = Math.pow(10, pctDecimals);
            return Math.round(v * p) / p;
        }

        function dayKey(d) {
            const x = startOfDay(d);
            return x.toISOString().slice(0, 10); // YYYY-MM-DD
        }
