<?php

return [
    'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', env('WALLBOARD_IP_ALLOWLIST', ''))))),
    'basic_auth_enabled' => env('WALLBOARD_BASIC_AUTH_ENABLED', false),
    'basic_auth_user' => env('WALLBOARD_BASIC_AUTH_USER', 'wallboard'),
    'basic_auth_pass' => env('WALLBOARD_BASIC_AUTH_PASS', ''),

    'burndown' => [
        // Working days in ISO format: 1=Mon ... 7=Sun
        // Mon–Fri (weekends plateau)
        'working_days' => [1, 2, 3, 4, 5],

        // Whether to build a DAILY series (recommended). If false, plots snapshot points only.
        'daily_series' => true,

        // Gridlines
        'grid' => [
            'enabled' => true,
            'y_ticks' => 5,          // horizontal grid count
            'x_week_lines' => true,  // vertical weekly markers
        ],

        // Tooltip on hover
        'tooltip' => [
            'enabled' => true,
        ],
    ],

    'display' => [
        'mode' => 'percent',
        'percent_basis' => 'current_scope',
        'show_raw_numbers' => false,
        'percent_decimals' => 0,
    ],


];
