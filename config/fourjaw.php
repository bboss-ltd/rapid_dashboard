<?php

return [
    'endpoints' => [
        'login' => env('FOURJAW_LOGIN_PATH', '/login'),
        'check_auth' => env('FOURJAW_CHECK_AUTH_PATH', '/check-auth-token'),
        'current_status' => env('FOURJAW_CURRENT_STATUS_PATH', '/analytics/machines/status/current'),
        'utilisation_summary' => env('FOURJAW_UTILISATION_SUMMARY_PATH', '/analytics/assets/utilisation/fixed-summary'),
    ],
    'auth_cache_ttl_minutes' => (int) env('FOURJAW_AUTH_CACHE_TTL', 480),
    'status_page_size' => (int) env('FOURJAW_STATUS_PAGE_SIZE', 200),
    'machines' => [
        // Secton
        '65363aeab508da286b7e30e9' => [
            [
                'id' => '65363b1d624017a94b443d8d',
                'display_name' => 'Secton - Amada EM2510 ',
                'parent_id' => '65363aeab508da286b7e30e9',
                'parent_display_name' => 'Secton',
            ]
        ],

        // Caldwell
        '68cbf044c8b7aeaf364ce361' => [
            [
                'id' => '65363b2a82e13c7d4c443e19',
                'display_name' => 'Caldwell - Amada EM2510 ',
                'parent_id' => '68cbf044c8b7aeaf364ce361',
                'parent_display_name' => 'Caldwell',
            ]
        ],

        // Jodrell
        '65363afebd64736e90443cb7' => [
            [
                'id' => '65363b08d259110a287e33a2',
                'display_name' => 'Jodrell Manual Amada AE2510',
                'parent_id' => '65363afebd64736e90443cb7',
                'parent_display_name' => 'Jodrell',
            ],
            [
                'id' => '65363b13b508da286b7e30eb',
                'display_name' => 'Jodrell Loader - Amada AE2510  ',
                'parent_id' => '65363afebd64736e90443cb7',
                'parent_display_name' => 'Jodrell',
            ]
        ]
    ]
];
