<?php
return [
    'app_url' => 'http://localhost:8000',    

    'db' => [
        'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port'      => $_ENV['DB_PORT'] ?? 3306,
        'database'  => $_ENV['DB_NAME'] ?? 'eventora_db',
        'username'  => $_ENV['DB_USER'] ?? 'root',
        'password'  => $_ENV['DB_PASS'] ?? '',
        'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    ],

    'jwt' => [
        'secret'    => $_ENV['JWT_SECRET'] ?? 'fallback_secret_key',
        'expiry'    => (int)($_ENV['JWT_EXPIRY'] ?? 3600)
    ]
];