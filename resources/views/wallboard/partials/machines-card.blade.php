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
        $durationFormat = (string) ($machineCfg['duration_format'] ?? 'auto');
        $showUtilisation = (bool) ($machineCfg['show_utilisation'] ?? true);

        $machines = $machines ?? [];
        $utilisationMap = $utilisation['per_machine'] ?? [];
        $utilisationMap = is_array($utilisationMap) ? $utilisationMap : [];

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

        $formatDuration = function ($minutes) use ($durationFormat): string {
            if ($minutes === null) {
                return '--';
            }
            if (is_string($minutes)) {
                $trimmed = trim($minutes);
                if (str_contains($trimmed, 'h')) {
                    return $trimmed;
                }
                if (str_ends_with($trimmed, 'm')) {
                    $trimmed = rtrim($trimmed, "m \t\n\r\0\x0B");
                }
                $minutes = is_numeric($trimmed) ? (int) $trimmed : null;
            } elseif (is_numeric($minutes)) {
                $minutes = (int) $minutes;
            } else {
                $minutes = null;
            }

            if ($minutes === null) {
                return '--';
            }

            if ($durationFormat === 'minutes') {
                return $minutes . 'm';
            }

            $days = intdiv($minutes, 1440);
            $hours = intdiv($minutes % 1440, 60);
            $mins = $minutes % 60;

            if ($durationFormat === 'hm') {
                $totalHours = intdiv($minutes, 60);
                $mins = $minutes % 60;
                if ($totalHours === 0) {
                    return $mins . 'm';
                }
                return $totalHours . 'h ' . $mins . 'm';
            }

            if ($days > 0) {
                $parts = [$days . 'd'];
                if ($hours > 0) {
                    $parts[] = $hours . 'h';
                }
                if ($mins > 0 || $hours === 0) {
                    $parts[] = $mins . 'm';
                }
                return implode(' ', $parts);
            }

            if ($minutes < 60) {
                return $minutes . 'm';
            }
            if ($hours > 0 && $mins === 0) {
                return $hours . 'h';
            }
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
                    @if($showUtilisation)
                        @php
                            $utilRaw = $utilisationMap[$m['id'] ?? ''] ?? null;
                            $utilPct = is_numeric($utilRaw) ? (int) round((float) $utilRaw) : null;
                        @endphp
                        @if($utilPct !== null)
                            <span> • </span>
                            <span>{{ $utilPct }}%</span>
                        @endif
                    @endif
                </div>
                <div class="trendMeta">{{ $m['name'] }}</div>
            </div>
        @endforeach
    </div>
</div>
