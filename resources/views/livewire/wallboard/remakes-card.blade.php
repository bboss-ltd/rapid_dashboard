<div class="chartCard" style="display:flex; flex-direction:column;" wire:poll.{{ $refreshSeconds }}s>
    <div wire:loading wire:target="refreshFromManual" class="badge" style="position:absolute; margin-top:-30px; margin-left:6px; font-size:12px;">Refreshing…</div>
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Remakes</div>
        </div>
        @if($debug && $lastRenderedAt)
            <div class="badge cardAction">Updated {{ \Illuminate\Support\Carbon::parse($lastRenderedAt)->format('H:i:s') }}</div>
        @endif
    </div>
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top:auto;">
        @php
            $isDateMode = !empty($remakesFor);
            $dateLabel = 'today';
            if ($isDateMode) {
                try {
                    $dateLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $remakesFor)->format('M j, Y');
                } catch (\Throwable) {
                    $dateLabel = $remakesFor;
                }
            }
            $trendToday = $remakeStats['trend_today'] ?? 'neutral';
            $trendSprint = $remakeStats['trend_sprint'] ?? 'neutral';
            $trendMonth = $remakeStats['trend_month'] ?? 'neutral';
            $trendIcon = fn($t) => $t === 'bad' ? '▲' : ($t === 'good' ? '▼' : '—');
            $hasPrevSprint = $isDateMode ? true : (($remakeStats['prev_sprint'] ?? null) !== null);
            $requestedToday = (int) ($remakeStats['requested_today'] ?? 0);
            $acceptedToday = (int) ($remakeStats['accepted_today'] ?? 0);
            $requestedPrevToday = (int) ($remakeStats['requested_prev_today'] ?? 0);
            $acceptedPrevToday = (int) ($remakeStats['accepted_prev_today'] ?? 0);
            $acceptedPct = $requestedToday > 0 ? (int) round(($acceptedToday / $requestedToday) * 100) : 0;
            $sprintValue = $isDateMode ? (int) ($remakeStats['today'] ?? 0) : (int) ($remakeStats['sprint'] ?? 0);
            $prevSprintValue = $isDateMode ? (int) ($remakeStats['prev_today'] ?? 0) : (int) ($remakeStats['prev_sprint'] ?? 0);
        @endphp
        <div class="trendInline" style="justify-content:space-evenly;">
            <div class="trendItem" title="{{ $isDateMode ? 'Remakes created on the selected date.' : 'Remake cards in the Remakes list on trello.' }}">
                <div class="trendValue">{{ $remakeTotal ?? 0 }}</div>
                <div class="trendMeta">{{ $isDateMode ? "Remakes on {$dateLabel}" : 'Current remakes in list' }}</div>
            </div>
            <div class="trendItem" title="{{ $isDateMode ? 'Requests vs accepted remakes created on the selected date. Percentage is accepted / requested.' : 'Requests vs accepted remakes created today. Percentage is accepted / requested.' }}">
                <div class="trendValue">{{ $requestedToday }}/{{ $acceptedToday }} ({{ $acceptedPct }}%)</div>
                <div class="trendMeta">{{ $isDateMode ? "Requested/accepted {$dateLabel}" : 'Requested/accepted today' }}</div>
            </div>
            <div class="trendItem" title="{{ $isDateMode ? 'Selected day requested/accepted compared with previous day in brackets.' : 'Current requested/accepted today compared with yesterday\'s requested/accepted values in brackets.' }}">
                <div class="trendValue trend-{{ $trendToday }}">
                    {{ $requestedToday }}/{{ $acceptedToday }}<span class="trendArrow">{{ $trendIcon($trendToday) }}</span>
                </div>
                <div class="trendMeta">{{ $isDateMode ? "Selected day vs previous day ({$requestedPrevToday}/{$acceptedPrevToday})" : "Today vs yesterday ({$requestedPrevToday}/{$acceptedPrevToday})" }}</div>
            </div>
            <div class="trendItem" title="{{ $isDateMode ? 'Total remakes on the selected date, excluding remove labels.' : 'Total requested remakes so far this sprint, excluding remove labels.' }}">
                <div class="trendValue">{{ $sprintValue }}</div>
                <div class="trendMeta">{{ $isDateMode ? 'Selected day total' : 'Sprint to date' }}</div>
            </div>
            <div class="trendItem" title="{{ $isDateMode ? 'Selected day compared to previous day (arrow shows trend).' : 'Sprint to date compared to last sprint\'s total (arrow shows trend).' }}">
                <div class="trendValue trend-{{ $trendSprint }}">
                    {{ $hasPrevSprint ? $sprintValue : '—' }}
                    <span class="trendArrow">{{ $trendIcon($trendSprint) }}</span>
                </div>
                <div class="trendMeta">{{ $isDateMode ? "Selected v previous day ({$prevSprintValue})" : 'Sprint v last sprint' }}</div>
            </div>
            <div class="trendItem" title="Projected month pace based on current sprint trend.">
                <div class="trendValue trend-{{ $trendMonth }}">{{ $remakeStats['month'] ?? 0 }}<span class="trendArrow">{{ $trendIcon($trendMonth) }}</span></div>
                <div class="trendMeta">Month pace</div>
            </div>
        </div>
    </div>
</div>
