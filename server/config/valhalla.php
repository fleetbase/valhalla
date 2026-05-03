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
];
