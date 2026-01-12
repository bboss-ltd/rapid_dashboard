<?php

return [
    // If set, only these IPs can access (unless basic auth passes).
    // Example: "192.168.1.10,192.168.1.11"
    'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', env('WALLBOARD_IP_ALLOWLIST', ''))))),

    // Enable/disable basic auth
    'basic_auth_enabled' => env('WALLBOARD_BASIC_AUTH_ENABLED', false),

    'basic_auth_user' => env('WALLBOARD_BASIC_AUTH_USER', 'wallboard'),
    'basic_auth_pass' => env('WALLBOARD_BASIC_AUTH_PASS', ''),
];
