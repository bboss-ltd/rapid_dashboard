<div class="chartCard" style="display:flex; flex-direction:column;">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Machines</div>
            <div class="cardSub">Demo data</div>
        </div>
    </div>
    @php
        $machineCfg = config('wallboard.machines', []);
        $statusColors = $machineCfg['status_colors'] ?? [];
        $idleWarn = (int) ($machineCfg['idle_warning_minutes'] ?? 30);
        $idleCrit = (int) ($machineCfg['idle_critical_minutes'] ?? 120);
        $loadGoodMin = (int) ($machineCfg['load_good_min'] ?? 50);
        $loadWarnMin = (int) ($machineCfg['load_warn_min'] ?? 20);
        $loadColors = $machineCfg['load_colors'] ?? [];

        $machines = [
            ['name' => 'Machine A', 'status' => 'running', 'idle_minutes' => 0, 'load_pct' => 42],
            ['name' => 'Machine B', 'status' => 'idle', 'idle_minutes' => 15, 'load_pct' => 0],
            ['name' => 'Machine C', 'status' => 'running', 'idle_minutes' => 0, 'load_pct' => 68],
            ['name' => 'Machine D', 'status' => 'maintenance', 'idle_minutes' => 0, 'load_pct' => null],
        ];

        $statusColorFor = function (array $m) use ($statusColors, $idleWarn, $idleCrit) {
            $status = strtolower((string) ($m['status'] ?? 'unknown'));
            if ($status === 'idle') {
                $idle = (int) ($m['idle_minutes'] ?? 0);
                return $idle > $idleCrit
                    ? ($statusColors['stopped'] ?? '#ff6b6b')
                    : ($statusColors['idle'] ?? '#ffb74a');
            }
            return $statusColors[$status] ?? ($statusColors['unknown'] ?? '#e8eefc');
        };

        $loadColorFor = function (array $m) use ($loadGoodMin, $loadWarnMin, $loadColors) {
            $load = $m['load_pct'];
            if ($load === null) return $loadColors['warn'] ?? '#ffb74a';
            if ($load >= $loadGoodMin) return $loadColors['good'] ?? '#65d38a';
            if ($load >= $loadWarnMin) return $loadColors['warn'] ?? '#ffb74a';
            return $loadColors['low'] ?? '#ff6b6b';
        };
    @endphp
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:auto;">
        @foreach($machines as $m)
            @php
                $statusColor = $statusColorFor($m);
                $loadColor = $loadColorFor($m);
            @endphp
            <div class="trendItem">
                <div class="trendValue">
                    <span style="color: {{ $statusColor }};">{{ ucfirst($m['status']) }}</span>
                    @if($m['load_pct'] !== null)
                        <span> • </span>
                        <span style="color: {{ $loadColor }};">{{ $m['load_pct'] }}% load</span>
                    @endif
                </div>
                <div class="trendMeta">{{ $m['name'] }}</div>
            </div>
        @endforeach
    </div>
</div>
