<?php
// ============================================================
// NChanger Server Configuration
// ============================================================

return [
    'app' => [
        'name'          => 'NChanger API',
        'version'       => '1.0.0',
        'api_version'   => 1,
        'env'           => getenv('APP_ENV') ?: 'production',
        'debug'         => getenv('APP_DEBUG') === 'true',
        'secret_key'    => getenv('APP_SECRET') ?: 'nc_sec_9cf5fd8d0cb247409884b06e8e8b1e09_change_me',
        'token_lifetime'=> 3600 * 24,       // 24 hours for access token
        'refresh_lifetime' => 3600 * 24 * 30 // 30 days for refresh token
    ],
    'database' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'dbname'   => getenv('DB_NAME') ?: 'nchanger_db',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4'
    ],
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-NChanger-Client', 'X-NChanger-API']
    ]
];
