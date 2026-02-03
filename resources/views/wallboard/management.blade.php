<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Management Wallboard</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            margin: 0;
            background: #0b0f19;
            color: #e8eefc;
        }

        .wrap {
            padding: 28px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            flex-wrap: wrap;
        }

        .title {
            font-size: 34px;
            font-weight: 800;
            line-height: 1.1;
        }

        .sub {
            opacity: .75;
            margin-top: 8px;
            font-size: 14px;
        }

        .card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 16px;
            padding: 16px;
        }

        .factoryRow {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .factoryTitle {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .cardHeader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .machineItem {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px 0;
        }

        .machineItem + .machineItem {
            border-top: 1px solid rgba(255, 255, 255, .08);
            padding-top: 10px;
        }

        .machineStatus {
            font-size: 16px;
            font-weight: 700;
        }

        .machineMeta {
            font-size: 12px;
            opacity: .7;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filters label {
            display: block;
            font-size: 12px;
            opacity: .7;
            margin-bottom: 6px;
        }

        .filters input,
        .filters select {
            background: rgba(255, 255, 255, .06);
            color: #e8eefc;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 8px;
            padding: 6px 10px;
        }

        .filters button {
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            padding: 10px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            text-align: left;
        }

        td.numeric,
        th.numeric {
            text-align: center;
        }

        th {
            font-weight: 600;
            opacity: .75;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-size: 10px;
        }

        .tableWrap {
            margin-top: 18px;
        }

        .pagination {
            margin-top: 12px;
            font-size: 12px;
        }

        .pagination a,
        .pagination span {
            color: #e8eefc;
            text-decoration: none;
            margin-right: 8px;
        }

        .pagination .disabled {
            opacity: .4;
        }

        .rangeNote {
            font-size: 12px;
            opacity: .6;
        }

        .cellHighlight {
            background: rgba(101, 211, 138, .18);
            border-radius: 8px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <div class="title">Management Wallboard</div>
            <div class="sub">Live factory status + remake reasons daily rollup</div>
        </div>
        <div class="rangeNote">
            Showing {{ $start->toDateString() }} → {{ $end->toDateString() }}
            <button id="manualRefreshBtn" style="margin-left:12px;background:transparent;border:1px solid rgba(255,255,255,.18);color:#e8eefc;padding:6px 10px;border-radius:999px;font-size:12px;cursor:pointer;">
                Refresh
            </button>
        </div>
    </div>

    @php
        $machineCfg = config('wallboard.machines', []);
        $statusColors = $machineCfg['status_colors'] ?? [];
        $idleWarn = (int) ($machineCfg['idle_warning_minutes'] ?? 30);
        $idleCrit = (int) ($machineCfg['idle_critical_minutes'] ?? 120);
        $durationFormat = (string) ($machineCfg['duration_format'] ?? 'auto');

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

    <div class="factoryRow">
        @foreach($factories as $factory)
            <div class="card">
                <div class="cardHeader">
                    <div class="factoryTitle">{{ $factory['name'] }}</div>
                </div>
                @forelse($factory['machines'] as $machine)
                    <div class="machineItem">
                        <div class="machineStatus">
                            <span style="color: {{ $statusColorFor($machine) }};">{{ ucfirst($machine['status'] ?? 'unknown') }}</span>
                            <span> • </span>
                            <span>{{ $formatDuration($machine['duration_minutes'] ?? null) }}</span>
                        </div>
                        <div class="machineMeta">{{ $machine['name'] ?? 'Unknown' }}</div>
                    </div>
                @empty
                    <div class="machineMeta">No machines configured.</div>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="card tableWrap">
        <div class="cardHeader">
            <div class="factoryTitle">Remake requests by day</div>
        </div>
        <div class="filters">
            <form method="get" class="filters">
                <div>
                    <label>From</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div>
                    <label>To</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}">
                </div>
                <div>
                    <label>Highlight</label>
                    <select name="highlight">
                        <option value="max" @selected($highlight === 'max')>Highest count</option>
                        <option value="delta" @selected($highlight === 'delta')>Largest increase</option>
                    </select>
                </div>
                <div>
                    <label>Delta days</label>
                    <input type="number" name="delta_days" min="1" value="{{ $deltaDays }}">
                </div>
                <div>
                    <label>Rows per page</label>
                    <select name="per_page">
                        @foreach(config('wallboard.pagination.options') as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit">Apply filter</button>
                </div>
            </form>
        </div>

        <div class="tableWrap">
            <table>
                <thead>
                <tr>
                    <th>Day</th>
                    @foreach($reasonOrder as $label)
                        <th class="numeric">{{ $label }}</th>
                    @endforeach
                    <th class="numeric">Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse($reasonRows as $row)
                    <tr>
                        @php
                            $dayLabel = $row['day'];
                            try {
                                $dayLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $row['day'])->format('D d/m/Y');
                            } catch (\Throwable) {
                            }
                        @endphp
                        <td>{{ $dayLabel }}</td>
                        @foreach($reasonOrder as $label)
                            @php($highlighted = !empty($row['highlights'][$label]))
                            <td class="numeric {{ $highlighted ? 'cellHighlight' : '' }}">{{ $row['counts'][$label] ?? 0 }}</td>
                        @endforeach
                        <td class="numeric">{{ $row['total'] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($reasonOrder) + 2 }}">No data for selected range.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{--        <div class="pagination">--}}
{{--            {{ $reasonRows->links() }}--}}
{{--        </div>--}}
{{--    </div>--}}
</div>
<script>
    (function () {
        const refreshSeconds = Number(@json(config('wallboard.refresh_seconds', 60)));
        const btn = document.getElementById('manualRefreshBtn');
        if (btn) {
            btn.addEventListener('click', () => location.reload());
        }
        if (refreshSeconds > 0) {
            setInterval(() => location.reload(), refreshSeconds * 1000);
        }
    })();
</script>
</body>
</html>
