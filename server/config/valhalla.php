<?php

/**
 * -------------------------------------------
 * Fleetbase Core API Configuration
 * -------------------------------------------
 */
return [
    'api' => [
        'version' => '0.0.1',
        'routing' => [
            'prefix' => 'valhalla',
            'internal_prefix' => 'int'
        ],
    ],
    'base_uri' => env('VALHALLA_BASE_URI', env('VALHALLA_HOST', 'https://valhalla1.openstreetmap.de')),
    'api_key' => env('VALHALLA_API_KEY'),
    // Let organization overrides point at private network hosts (self-hosted installs only).
    'allow_private_hosts' => env('VALHALLA_ALLOW_PRIVATE_HOSTS', false),

    /*
    | Fleetbase Hosted Routing (routing.fleetbase.io). When the Valhalla host is
    | this service, the admin settings panel shows the key's plan and usage
    | and links to Fleetbase Cloud to get, upgrade or manage it.
    */
    'hosted' => [
        'domain'      => env('VALHALLA_HOSTED_DOMAIN', 'routing.fleetbase.io'),
        'console_url' => env('VALHALLA_HOSTED_CONSOLE_URL', 'https://console.fleetbase.io'),
        'timeout'     => env('VALHALLA_HOSTED_TIMEOUT', 5),
    ],
];
