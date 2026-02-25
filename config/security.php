<?php
declare(strict_types=1);

return [
    'jwt_secret' => getenv('JWT_SECRET') ?: 'change-this-in-production',
    'jwt_issuer' => getenv('JWT_ISSUER') ?: 'financial-saas',
    'access_ttl' => (int) (getenv('JWT_ACCESS_TTL') ?: 900),
    'refresh_ttl' => (int) (getenv('JWT_REFRESH_TTL') ?: 1209600),
    'csrf_header' => 'X-CSRF-Token',
    'rate_limit_per_minute' => (int) (getenv('RATE_LIMIT_PER_MINUTE') ?: 120),
    'login_max_attempts' => (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5),
    'login_lock_minutes' => (int) (getenv('LOGIN_LOCK_MINUTES') ?: 15),
    'password_algo' => PASSWORD_ARGON2ID,
];
