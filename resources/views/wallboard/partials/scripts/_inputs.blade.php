        // ========= Inputs from Laravel =========
        const snapshotSeries = @json($series ?? []);
        const refreshSeconds = Number(@json($refreshSeconds ?? 60));
        const remakeReasonStats = @json($remakeReasonStats ?? []);
        const sprint = {
            id: @json($sprint->id),
            starts_at: @json(optional($sprint->starts_at)?->toIso8601String()),
            ends_at: @json(optional($sprint->ends_at)?->toIso8601String()),
            closed_at: @json(optional($sprint->closed_at)?->toIso8601String()),
        };

        const cfg = @json(config('wallboard.burndown', []));
        const display = cfg?.display ?? {};
        const displayMode = 'percent'; // force percent to avoid showing raw story points
        const percentBasis = display.percent_basis ?? 'current_scope'; // 'current_scope' | 'start_scope'
        const showRaw = false;
        const pctDecimals = Number(display.percent_decimals ?? 0);

        const workingDays = (cfg?.working_days ?? [1, 2, 3, 4, 5]); // ISO 1..7
        const gridEnabled = !!(cfg?.grid?.enabled ?? true);
        const yTicks = Number(cfg?.grid?.y_ticks ?? 5);
        const xWeekLines = !!(cfg?.grid?.x_week_lines ?? true);
        const tooltipEnabled = !!(cfg?.tooltip?.enabled ?? true);
        const useDaily = !!(cfg?.daily_series ?? true);
