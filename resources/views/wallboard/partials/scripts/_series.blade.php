        // ========= Normalize snapshots =========
        const snaps = (snapshotSeries ?? [])
            .map(s => ({
                taken_at: toDate(s.taken_at || s.takenAt || s.date || null),
                type: s.type || s.snapshot_type || 'snapshot',
                remaining_points: Number(s.remaining_points ?? 0),
                done_points: Number(s.done_points ?? 0),
                scope_points: Number(s.scope_points ?? 0),
                raw: s,
            }))
            .filter(s => s.taken_at)
            .sort((a, b) => a.taken_at - b.taken_at);

        // ========= Build series =========
        function buildDailySeries() {
            if (!snaps.length) return [];

            const sprintStart = startOfDay(toDate(sprint.starts_at) ?? snaps[0].taken_at);
            const sprintEndBase = toDate(sprint.ends_at) ?? snaps[snaps.length - 1].taken_at;
            const sprintEnd = startOfDay(sprintEndBase);

            const today = startOfDay(new Date());
            const endDay = (toDate(sprint.closed_at) ? sprintEnd : (today < sprintEnd ? today : sprintEnd));

            const days = [];
            for (let d = new Date(sprintStart); d <= sprintEnd; d.setDate(d.getDate() + 1)) {
                days.push(new Date(d));
            }

            let cursor = 0;
            let last = null;
            const daily = [];

            for (const day of days) {
                const cutoff = endOfDay(day);
                while (cursor < snaps.length && snaps[cursor].taken_at <= cutoff) {
                    last = snaps[cursor];
                    cursor++;
                }

                const isFuture = day > endDay;
                if (last) {
                    daily.push({
                        date: new Date(day),
                        remaining_points: isFuture ? null : last.remaining_points,
                        done_points: isFuture ? null : last.done_points,
                        scope_points: last.scope_points,
                        last_snapshot_at: last.taken_at,
                        last_snapshot_type: last.type,
                        is_future: isFuture,
                    });
                } else {
                    daily.push({
                        date: new Date(day),
                        remaining_points: null,
                        done_points: null,
                        scope_points: 0,
                        last_snapshot_at: null,
                        last_snapshot_type: null,
                        is_future: isFuture,
                    });
                }
            }

            return daily;
        }

        function buildSnapshotPointSeries() {
            return snaps.map(s => ({
                date: s.taken_at,
                remaining_points: s.remaining_points,
                done_points: s.done_points,
                scope_points: s.scope_points,
                last_snapshot_at: s.taken_at,
                last_snapshot_type: s.type,
            }));
        }

        function buildIdealSeries(actual) {
            if (!actual.length) return [];

            const startDate = startOfDay(actual[0].date);
            const endDate = startOfDay(actual[actual.length - 1].date);

            // start scope = first non-zero, else max seen
            let startScope = 0;
            for (const p of actual) {
                const s = Number(p.scope_points ?? 0);
                if (s > 0) {
                    startScope = s;
                    break;
                }
            }
            if (startScope <= 0) {
                startScope = Math.max(...actual.map(p => Number(p.scope_points ?? 0)), 0);
            }
            if (startScope <= 0) return [];

            // count working days inclusive
            let workingCount = 0;
            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                if (workingDays.includes(isoDow(d))) workingCount++;
            }
            if (workingCount <= 0) workingCount = 1;

            const burnPerWorkDay = startScope / workingCount;

            let remaining = startScope;
            const ideal = [];
            for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                const day = new Date(d);
                ideal.push({
                    date: new Date(day),
                    remaining_points: Math.max(0, remaining),
                    scope_points: startScope,
                });

                if (workingDays.includes(isoDow(day))) {
                    remaining -= burnPerWorkDay;
                } // else plateau
            }

            return ideal;
        }

        const actual = useDaily ? buildDailySeries() : buildSnapshotPointSeries();
        const idealRaw = buildIdealSeries(actual);

        // Align ideal to actual by day-key (fixes "ideal line missing" issues)
        const idealMap = new Map(idealRaw.map(p => [dayKey(p.date), p]));
        const ideal = actual.map(p => idealMap.get(dayKey(p.date)) ?? null);

        // choose start scope for percent basis
        let startScopeForBasis = 0;
        for (const p of actual) {
            const s = Number(p.scope_points ?? 0);
            if (s > 0) {
                startScopeForBasis = s;
                break;
            }
        }
        if (startScopeForBasis <= 0) {
            startScopeForBasis = Math.max(...actual.map(p => Number(p.scope_points ?? 0)), 0);
        }

        function toDisplayY(point) {
            if (displayMode === 'points') return Number(point.remaining_points ?? 0);

            const denom =
                percentBasis === 'start_scope'
                    ? (startScopeForBasis || 0)
                    : Number(point.scope_points ?? 0);

            return roundPct(safePct(Number(point.remaining_points ?? 0), denom));
        }

        function toIdealDisplayY(point) {
            if (!point) return null;
            if (displayMode === 'points') return Number(point.remaining_points ?? 0);

            const denom =
                percentBasis === 'start_scope'
                    ? (startScopeForBasis || Number(point.scope_points ?? 0))
                    : Number(point.scope_points ?? startScopeForBasis ?? 0);

            return roundPct(safePct(Number(point.remaining_points ?? 0), denom));
        }
