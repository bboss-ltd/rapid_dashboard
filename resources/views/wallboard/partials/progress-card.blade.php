<div class="chartCard">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Sprint progress</div>
            <div class="cardSub">Completed vs Remaining</div>
        </div>
    </div>

    <div style="position: relative; margin-top: 12px;">
        <canvas id="progressDonut" style="width: 100%; height: 220px;"></canvas>
        <div id="progressLabel" style="
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    pointer-events:none;
">
            <div id="progressPct" style="font-size: 34px; font-weight: 800;">—%</div>
            <div style="font-size: 12px; opacity: .8;">complete</div>
        </div>
    </div>

    <div id="syncStatus" style="margin-top: 8px; font-size: 12px; opacity: .8;"></div>
</div>
