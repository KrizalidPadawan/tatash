<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Security\JwtHandler;
use PDO;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly JwtHandler $jwt,
        private readonly PDO $db,
        private readonly array $security
    ) {}

    public function login(string $email, string $password, string $tenantSlug, string $ip): array
    {
        $attempts = $this->users->countRecentFailedAttempts($email, $ip, $this->security['login_lock_minutes']);
        if ($attempts >= $this->security['login_max_attempts']) {
            throw new \RuntimeException('Too many failed login attempts. Try again later.');
        }

        $user = $this->users->findByEmailAndTenantSlug($email, $tenantSlug);
        if (!$user || !(bool) $user['active'] || !password_verify($password, $user['password_hash'])) {
            $this->users->recordLoginAttempt($email, $ip);
            throw new \RuntimeException('Invalid credentials');
        }

        $this->users->clearLoginAttempts($email, $ip);

        $claims = [
            'sub' => (int) $user['id'],
            'tenant_id' => (int) $user['tenant_id'],
            'role_id' => (int) $user['role_id'],
        ];

        $access = $this->jwt->issueAccessToken($claims, $this->security['access_ttl']);
        $refresh = $this->jwt->issueRefreshToken($claims, $this->security['refresh_ttl']);

        $payload = $this->jwt->decode($refresh);
        $sql = 'INSERT INTO refresh_tokens (user_id, tenant_id, jti, token_hash, expires_at)
                VALUES (:user_id, :tenant_id, :jti, :token_hash, :expires_at)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $claims['sub'],
            'tenant_id' => $claims['tenant_id'],
            'jti' => $payload['jti'],
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => gmdate('Y-m-d H:i:s', $payload['exp']),
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => $this->security['access_ttl'],
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $payload = $this->jwt->decode($refreshToken);
        if (($payload['typ'] ?? '') !== 'refresh') {
            throw new \RuntimeException('Invalid refresh token type');
        }

        $sql = 'SELECT id, revoked_at FROM refresh_tokens
                WHERE tenant_id = :tenant_id AND user_id = :user_id AND jti = :jti
                AND token_hash = :token_hash AND expires_at > NOW() LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => (int) $payload['tenant_id'],
            'user_id' => (int) $payload['sub'],
            'jti' => $payload['jti'],
            'token_hash' => hash('sha256', $refreshToken),
        ]);

        $stored = $stmt->fetch();
        if (!$stored || $stored['revoked_at'] !== null) {
            throw new \RuntimeException('Refresh token revoked or invalid');
        }

        $this->db->prepare('UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id')
            ->execute(['id' => $stored['id']]);

        $claims = [
            'sub' => (int) $payload['sub'],
            'tenant_id' => (int) $payload['tenant_id'],
            'role_id' => (int) $payload['role_id'],
        ];

        $newAccess = $this->jwt->issueAccessToken($claims, $this->security['access_ttl']);
        $newRefresh = $this->jwt->issueRefreshToken($claims, $this->security['refresh_ttl']);
        $newPayload = $this->jwt->decode($newRefresh);

        $this->db->prepare('INSERT INTO refresh_tokens (user_id, tenant_id, jti, token_hash, expires_at)
                VALUES (:user_id, :tenant_id, :jti, :token_hash, :expires_at)')->execute([
            'user_id' => $claims['sub'],
            'tenant_id' => $claims['tenant_id'],
            'jti' => $newPayload['jti'],
            'token_hash' => hash('sha256', $newRefresh),
            'expires_at' => gmdate('Y-m-d H:i:s', $newPayload['exp']),
        ]);

        return [
            'access_token' => $newAccess,
            'refresh_token' => $newRefresh,
            'token_type' => 'Bearer',
            'expires_in' => $this->security['access_ttl'],
        ];
    }
}
