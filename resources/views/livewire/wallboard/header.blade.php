<div class="top" wire:poll.{{ $refreshSeconds }}s>
    <div>
        <div class="sub">
            <x-ui.datetime :value="$sprint->starts_at" :format="config('display.date')"/>
            →
            <x-ui.datetime :value="$sprint->ends_at" :format="config('display.date')"/>
            <button
                class="badge headerAction"
                wire:click="sync"
                wire:loading.attr="disabled"
                wire:target="sync"
                data-sync-button
            >
                <span wire:loading.remove wire:target="sync" data-sync-text>Manual re-sync</span>
                <span wire:loading wire:target="sync">Syncing…</span>
            </button>
        </div>
    </div>

    <div class="headerMeta">
        @php($lastRefresh = now())
        @php($lastPoll = $sprint->last_polled_at)
        <div
            class="badge"
            data-last-refresh="{{ $lastRefresh->toIso8601String() }}"
            data-refresh-seconds="{{ $refreshSeconds }}"
        >
            Last refresh:
            <x-ui.datetime
                :value="$lastRefresh"
                :format="$debug ? config('display.datetime_seconds') : config('display.datetime')"
            />
            @if($debug && $lastPoll)
                <span style="opacity:.7;"> • Poll: {{ $lastPoll->format('H:i:s') }}</span>
            @endif
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            if (window.__wbHeaderSyncInit) return;
            window.__wbHeaderSyncInit = true;

            function flashSync() {
                const button = document.querySelector('[data-sync-button]');
                if (!button) return;
                const textEl = button.querySelector('[data-sync-text]');
                if (!textEl) return;
                const original = textEl.dataset.defaultText || textEl.textContent;
                textEl.dataset.defaultText = original;
                textEl.textContent = 'Synced';
                button.classList.add('headerAction--synced');
                setTimeout(() => {
                    textEl.textContent = original;
                    button.classList.remove('headerAction--synced');
                }, 1500);
            }

            function startCountdown() {
                const badge = document.querySelector('[data-last-refresh]');
                if (!badge) return;
                const countdownEl = badge.querySelector('[data-refresh-countdown]');
                if (!countdownEl) return;

                const refreshSeconds = Number(badge.dataset.refreshSeconds || '60');
                if (!refreshSeconds || Number.isNaN(refreshSeconds)) return;

                function renderCountdown() {
                    if (!window.__wbNextPollAt) {
                        const lastRefreshRaw = badge.dataset.lastRefresh || '';
                        const lastRefresh = Date.parse(lastRefreshRaw);
                        const base = Number.isNaN(lastRefresh) ? Date.now() : lastRefresh;
                        window.__wbNextPollAt = base + (refreshSeconds * 1000);
                    }
                    const remainingMs = Math.max(0, window.__wbNextPollAt - Date.now());
                    const remaining = Math.ceil(remainingMs / 1000);
                    countdownEl.textContent = ` • ${remaining}s`;
                }

                renderCountdown();
                if (window.__wbCountdownTimer) {
                    clearInterval(window.__wbCountdownTimer);
                }
                window.__wbCountdownTimer = setInterval(renderCountdown, 1000);
            }

            function resetCountdownNow() {
                const badge = document.querySelector('[data-last-refresh]');
                if (!badge) return;
                const refreshSeconds = Number(badge.dataset.refreshSeconds || '60');
                if (!refreshSeconds || Number.isNaN(refreshSeconds)) return;
                const now = Date.now();
                badge.dataset.lastRefresh = new Date(now).toISOString();
                window.__wbNextPollAt = now + (refreshSeconds * 1000);
                startCountdown();
            }

            function setSyncing(isSyncing) {
                document.body.setAttribute('data-syncing', isSyncing ? '1' : '0');
            }

            document.addEventListener('livewire:init', () => {
                setSyncing(false);
                if (window.Livewire && typeof window.Livewire.on === 'function') {
                    window.Livewire.on('wallboard-synced', () => {
                        flashSync();
                        setSyncing(false);
                    });
                    window.Livewire.on('wallboard-refresh', resetCountdownNow);
                    window.Livewire.on('wallboard-syncing', () => {
                        setSyncing(true);
                    });
                    if (typeof window.Livewire.hook === 'function') {
                        window.Livewire.hook('morph.updated', ({ component }) => {
                            if (!component || typeof component.name !== 'string') return;
                            if (!component.name.startsWith('wallboard.')) return;
                            resetCountdownNow();
                        });
                    }
                }
                resetCountdownNow();
                startCountdown();
            });
        })();
    </script>
@endpush
