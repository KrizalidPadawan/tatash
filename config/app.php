<?php
declare(strict_types=1);

return [
    'name' => 'Financial SaaS',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
    'base_path' => dirname(__DIR__),
    'storage_path' => dirname(__DIR__) . '/storage',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'api_prefix' => '/api/v1',
];
