<div class="chartCard" style="display:flex; flex-direction:column;">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Remakes</div>
        </div>
    </div>
    <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top:auto;">
        @php
            $trendToday = $remakeStats['trend_today'] ?? 'neutral';
            $trendSprint = $remakeStats['trend_sprint'] ?? 'neutral';
            $trendMonth = $remakeStats['trend_month'] ?? 'neutral';
            $trendIcon = fn($t) => $t === 'bad' ? '▲' : ($t === 'good' ? '▼' : '—');
            $hasPrevSprint = ($remakeStats['prev_sprint'] ?? null) !== null;
            $requestedToday = (int) ($remakeStats['requested_today'] ?? 0);
            $acceptedToday = (int) ($remakeStats['accepted_today'] ?? 0);
            $requestedPrevToday = (int) ($remakeStats['requested_prev_today'] ?? 0);
            $acceptedPrevToday = (int) ($remakeStats['accepted_prev_today'] ?? 0);
            $acceptedPct = $requestedToday > 0 ? (int) round(($acceptedToday / $requestedToday) * 100) : 0;
        @endphp
        <div class="trendInline" style="justify-content:space-evenly;">
            <div class="trendItem" title="Remake cards in the Remakes list on trello">
                <div class="trendValue">{{ $remakeTotal ?? 0 }}</div>
                <div class="trendMeta">Current remakes in list</div>
            </div>
            <div class="trendItem" title="Requests vs accepted remakes created today. Percentage is accepted / requested.">
                <div class="trendValue">{{ $requestedToday }}/{{ $acceptedToday }} ({{ $acceptedPct }}%)</div>
                <div class="trendMeta">Requested/accepted today</div>
            </div>
            <div class="trendItem" title="Current requested/accepted today compared with yesterday's requested/accepted values in brackets.">
                <div class="trendValue trend-{{ $trendToday }}">
                    {{ $requestedToday }}/{{ $acceptedToday }}<span class="trendArrow">{{ $trendIcon($trendToday) }}</span>
                </div>
                <div class="trendMeta">Today vs yesterday ({{ $requestedPrevToday }}/{{ $acceptedPrevToday }})</div>
            </div>
            <div class="trendItem" title="Total requested remakes so far this sprint, excluding remove labels.">
                <div class="trendValue">{{ $remakeStats['sprint'] ?? 0 }}</div>
                <div class="trendMeta">Sprint to date</div>
            </div>
            <div class="trendItem" title="Sprint to date compared to last sprint's total (arrow shows trend).">
                <div class="trendValue trend-{{ $trendSprint }}">
                    {{ $hasPrevSprint ? ($remakeStats['sprint'] ?? 0) : '—' }}
                    <span class="trendArrow">{{ $trendIcon($trendSprint) }}</span>
                </div>
                <div class="trendMeta">Sprint v last sprint</div>
            </div>
            <div class="trendItem" title="Projected month pace based on current sprint trend.">
                <div class="trendValue trend-{{ $trendMonth }}">{{ $remakeStats['month'] ?? 0 }}<span class="trendArrow">{{ $trendIcon($trendMonth) }}</span></div>
                <div class="trendMeta">Month pace</div>
            </div>
        </div>
    </div>
</div>
