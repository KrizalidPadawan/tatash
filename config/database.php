<?php
declare(strict_types=1);

return [
    'driver' => getenv('DB_CONNECTION') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '192.168.1.151',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'financial_saas',
    'username' => getenv('DB_USERNAME') ?: 'fincontrol_user',
    'password' => getenv('DB_PASSWORD') ?: 'TU_PASSWORD',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'persistent' => true,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
