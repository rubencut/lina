<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:4177',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://localhost:8001',
        'http://127.0.0.1',
        'http://127.0.0.1:4177',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8001',
        'http://frontend.test',
        'http://cms-frontend.test',
        'http://cms.test',
    ],
    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?test(:[0-9]+)?$#i',
        '#^https?://localhost(:[0-9]+)?$#i',
        '#^https?://127\.0\.0\.1(:[0-9]+)?$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
