        // ========= DOM =========
        const canvas = document.getElementById('burndown');
        const chartWrap = document.getElementById('chartWrap');
        const tooltip = document.getElementById('chartTooltip');
        const donut = document.getElementById('progressDonut');
        const pctEl = document.getElementById('progressPct');
        const remakeChart = document.getElementById('remakeReasonChart');
        const remakeLegend = document.getElementById('remakeReasonLegend');
        const syncBtn = document.getElementById('manualSyncBtn');
        const syncStatus = document.getElementById('syncStatus');

        if (!canvas) return;
        const ctx = canvas.getContext('2d');
