<div class="chartCard">
    <div class="cardHeader">
        <div>
            <div class="cardTitle">Burndown</div>
            <div class="cardSub">Remaining progress over time</div>
        </div>
    </div>
    <div style="margin-top: 10px;">
        <div id="chartWrap" style="position: relative;">
            <canvas id="burndown"></canvas>

            <div id="chartTooltip" style="
    position:absolute;
    display:none;
    pointer-events:none;
    max-width: 320px;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(10,14,24,.92);
    color: #e8eefc;
    font-size: 12px;
    line-height: 1.35;
    box-shadow: 0 12px 30px rgba(0,0,0,.35);
    backdrop-filter: blur(6px);
"></div>
        </div>

    </div>
    <div class="foot">
        <div>
            Based on snapshots; historical view stays consistent over time.
        </div>
    </div>
</div>
