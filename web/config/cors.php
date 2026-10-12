<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
| The mobile app is a native client and talks to /api/* with Bearer tokens, so
| it is not subject to browser CORS. These settings matter for Expo web, the
| admin tools and other browser clients.
|
| Development allows every origin. In production set CORS_ALLOWED_ORIGINS to a
| comma-separated list (e.g. "https://faithfellows.app,https://app.faithfellows.app").
|
| supports_credentials stays false on purpose: the API authenticates with a
| Bearer token header, not cookies, and browsers reject "Allow-Credentials"
| combined with a wildcard origin anyway.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 0,

    'supports_credentials' => false,

];
