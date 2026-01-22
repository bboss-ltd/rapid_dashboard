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

        // ========= Manual re-sync =========
        if (syncBtn) {
            syncBtn.addEventListener('click', () => runSync({showUi: true, reloadOnSuccess: true}));
        }

        // ========= Auto refresh (sync then reload) =========
        if (refreshSeconds > 0) {
            setTimeout(() => {
                runSync({showUi: false, reloadOnSuccess: true, reloadOnFail: true});
            }, refreshSeconds * 1000);
        }
