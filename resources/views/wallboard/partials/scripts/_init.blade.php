        // ========= Initial draw =========
        latest = [...actual].reverse().find(p => p && p.remaining_points !== null) || null;
        if (latest) {
            drawDonut(Number(latest.done_points ?? 0), Number(latest.remaining_points ?? 0));
        } else {
            drawDonut(0, 0);
        }

        drawRemakeReasons();
        draw();
