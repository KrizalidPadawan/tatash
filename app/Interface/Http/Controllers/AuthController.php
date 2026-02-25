<?php
declare(strict_types=1);

namespace App\Interface\Http\Controllers;

use App\Application\Services\AuthService;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Security\JwtHandler;
use App\Interface\Http\Request;

final class AuthController extends BaseController
{
    private function service(): AuthService
    {
        $security = require dirname(__DIR__, 4) . '/config/security.php';
        $jwt = new JwtHandler($security['jwt_secret'], $security['jwt_issuer']);

        $db = Connection::get();
        return new AuthService(new UserRepository($db), $jwt, $db, $security);
    }

    public function login(Request $request)
    {
        $email = strtolower(trim((string) ($request->body['email'] ?? '')));
        $password = (string) ($request->body['password'] ?? '');
        $tenantSlug = trim((string) ($request->body['tenant_slug'] ?? ''));

        if ($email === '' || $password === '' || $tenantSlug === '') {
            return $this->fail('validation_error', 'Tenant, email and password are required', 422);
        }

        try {
            $tokens = $this->service()->login($email, $password, $tenantSlug, $request->ip);
        } catch (\Throwable $e) {
            return $this->fail('auth_error', $e->getMessage(), 401);
        }

        setcookie('refresh_token', $tokens['refresh_token'], [
            'expires' => time() + 1209600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return $this->ok($tokens);
    }

    public function refresh(Request $request)
    {
        $refresh = (string) ($request->body['refresh_token'] ?? ($_COOKIE['refresh_token'] ?? ''));
        if ($refresh === '') {
            return $this->fail('validation_error', 'Missing refresh token', 422);
        }

        try {
            $tokens = $this->service()->refresh($refresh);
        } catch (\Throwable $e) {
            return $this->fail('auth_error', $e->getMessage(), 401);
        }

        return $this->ok($tokens);
    }
}
