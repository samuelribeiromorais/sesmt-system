<?php

return [
    'host'       => $_ENV['SMTP_HOST'] ?? '',
    'port'       => (int)($_ENV['SMTP_PORT'] ?? 587),
    'username'   => $_ENV['SMTP_USER'] ?? '',
    'password'   => $_ENV['SMTP_PASS'] ?? '',
    'encryption' => 'tls',
    'from_name'  => $_ENV['SMTP_FROM_NAME'] ?? 'SESMT TSE',
    'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? '',
];
