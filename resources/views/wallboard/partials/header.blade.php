<div class="top">
    <div>
{{--        <div class="title">Sprint Overview</div>--}}
        <div class="sub">
            <x-ui.datetime :value="$sprint->starts_at" :format="config('display.date')"/>
            →
            <x-ui.datetime :value="$sprint->ends_at" :format="config('display.date')"/>
            <button id="manualSyncBtn"
                    class="badge headerAction">
                Manual re-sync
            </button>
        </div>
    </div>

    <div class="headerMeta">
        <div class="badge">
            Last refresh:
            <x-ui.datetime :value="now()" :format="config('display.datetime_seconds')"/>
        </div>

    </div>
</div>

@push('scripts')
    <script>
        (function () {
            // Expose wallboard payload + helpers for component scripts.
            const snapshotSeries = @json($series ?? []);
            const refreshSeconds = Number(@json($refreshSeconds ?? 60));
            const remakeReasonStats = @json($remakeReasonStats ?? []);
            const sprint = {
                id: @json($sprint->id),
                starts_at: @json(optional($sprint->starts_at)?->toIso8601String()),
                ends_at: @json(optional($sprint->ends_at)?->toIso8601String()),
                closed_at: @json(optional($sprint->closed_at)?->toIso8601String()),
            };

            const cfg = @json(config('wallboard.burndown', []));
            const display = cfg?.display ?? {};
            const displayMode = 'percent';
            const percentBasis = display.percent_basis ?? 'current_scope';
            const pctDecimals = Number(display.percent_decimals ?? 0);
            const workingDays = (cfg?.working_days ?? [1, 2, 3, 4, 5]);
            const useDaily = !!(cfg?.daily_series ?? true);

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
                const js = d.getDay();
                return js === 0 ? 7 : js;
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
                return x.toISOString().slice(0, 10);
            }

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
                    }
                }

                return ideal;
            }

            const actual = useDaily ? buildDailySeries() : buildSnapshotPointSeries();
            const idealRaw = buildIdealSeries(actual);
            const idealMap = new Map(idealRaw.map(p => [dayKey(p.date), p]));
            const ideal = actual.map(p => idealMap.get(dayKey(p.date)) ?? null);

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

            window.Wallboard = {
                snapshotSeries,
                refreshSeconds,
                remakeReasonStats,
                sprint,
                cfg,
                displayMode,
                percentBasis,
                pctDecimals,
                workingDays,
                actual,
                ideal,
                startScopeForBasis,
                helpers: {
                    toDate,
                    startOfDay,
                    endOfDay,
                    isoDow,
                    safePct,
                    roundPct,
                    dayKey,
                },
                onResize: [],
            };

            window.addEventListener('resize', () => {
                // Re-render charts on resize.
                window.Wallboard.onResize.forEach(fn => fn());
            });
        })();
    </script>

    <script>
        (function () {
            // Manual sync + auto-refresh cycle.
            const syncBtn = document.getElementById('manualSyncBtn');
            const syncStatus = document.getElementById('syncStatus');
            const refreshSeconds = window.Wallboard?.refreshSeconds ?? 60;

            let syncInFlight = false;

            async function runSync({showUi = false, reloadOnSuccess = false, reloadOnFail = false} = {}) {
                if (syncInFlight) return false;
                syncInFlight = true;

                const prevText = syncBtn?.textContent || 'Manual re-sync';
                if (showUi && syncBtn) {
                    syncBtn.disabled = true;
                    syncBtn.textContent = 'Syncing…';
                }
                if (syncStatus) {
                    syncStatus.textContent = showUi ? 'Requesting sync…' : 'Auto-refresh: syncing…';
                }

                try {
                    const res = await fetch(@json(route('wallboard.sprint.sync', $sprint)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json?.message || `HTTP ${res.status}`);

                    if (syncStatus) syncStatus.textContent = json?.message || 'Sync complete.';
                    if (reloadOnSuccess) setTimeout(() => location.reload(), 700);
                    return true;
                } catch (e) {
                    if (syncStatus) syncStatus.textContent = `Sync failed: ${e.message}`;
                    if (reloadOnFail) setTimeout(() => location.reload(), 700);
                    return false;
                } finally {
                    if (showUi && syncBtn) {
                        syncBtn.disabled = false;
                        syncBtn.textContent = prevText;
                    }
                    syncInFlight = false;
                }
            }

            if (syncBtn) {
                syncBtn.addEventListener('click', () => runSync({showUi: true, reloadOnSuccess: true}));
            }

            if (refreshSeconds > 0) {
                setTimeout(() => {
                    runSync({showUi: false, reloadOnSuccess: true, reloadOnFail: true});
                }, refreshSeconds * 1000);
            }
        })();
    </script>
@endpush
