<?php

return [
    'name'     => $_ENV['APP_NAME'] ?? 'SESMT - TSE Engenharia',
    'url'      => $_ENV['APP_URL'] ?? 'http://localhost',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    'aes_key'  => $_ENV['AES_KEY'] ?? '',
    'upload'   => [
        'max_size'      => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760),
        'allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'application/pdf'),
        'path'          => dirname(__DIR__, 2) . '/storage/uploads',
    ],
    'session'  => [
        'lifetime'     => 900, // 15 min
        'max_attempts' => 5,
        'lockout_time' => 1200, // 20 min
    ],
    'alerts'   => [
        'days_before' => [30, 15, 7],
    ],
];
