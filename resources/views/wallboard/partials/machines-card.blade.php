<div class="chartCard" style="display:flex; flex-direction:column;">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Machines</div>
        </div>
    </div>
    @php
        $machineCfg = config('wallboard.machines', []);
        $statusColors = $machineCfg['status_colors'] ?? [];
        $idleWarn = (int) ($machineCfg['idle_warning_minutes'] ?? 30);
        $idleCrit = (int) ($machineCfg['idle_critical_minutes'] ?? 120);

        $machines = $machines ?? [];

        $statusColorFor = function (array $m) use ($statusColors, $idleWarn, $idleCrit) {
            $status = strtolower((string) ($m['status'] ?? 'unknown'));
            if ($status === 'idle') {
                $idle = (int) ($m['duration_minutes'] ?? 0);
                return $idle > $idleCrit
                    ? ($statusColors['stopped'] ?? '#ff6b6b')
                    : ($statusColors['idle'] ?? '#ffb74a');
            }
            return $statusColors[$status] ?? ($statusColors['unknown'] ?? '#e8eefc');
        };

        $formatDuration = function (?int $minutes): string {
            if ($minutes === null) {
                return '--';
            }
            if ($minutes < 60) {
                return $minutes . 'm';
            }
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;
            return $hours . 'h ' . $mins . 'm';
        };
    @endphp
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:auto;">
        @foreach($machines as $m)
            @php
                $statusColor = $statusColorFor($m);
            @endphp
            <div class="trendItem">
                <div class="machine trendValue">
                    <span style="color: {{ $statusColor }};">{{ ucfirst($m['status']) }}</span>
                    <span> • </span>
                    <span>{{ $formatDuration($m['duration_minutes'] ?? null) }}</span>
                </div>
                <div class="trendMeta">{{ $m['name'] }}</div>
            </div>
        @endforeach
    </div>
</div>
