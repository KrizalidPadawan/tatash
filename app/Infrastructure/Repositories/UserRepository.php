<?php
declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findByEmailAndTenantSlug(string $email, string $tenantSlug): ?array
    {
        $sql = 'SELECT u.id, u.tenant_id, u.role_id, u.email, u.password_hash, u.active
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE u.email = :email AND t.slug = :tenant_slug
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email, 'tenant_slug' => $tenantSlug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function hasPermission(int $roleId, string $permission): bool
    {
        $sql = 'SELECT 1
                FROM role_permissions rp
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id AND p.code = :code
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role_id' => $roleId, 'code' => $permission]);
        return (bool) $stmt->fetchColumn();
    }

    public function recordLoginAttempt(string $email, string $ip): void
    {
        $sql = 'INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (:email, :ip, NOW())';
        $this->db->prepare($sql)->execute(['email' => $email, 'ip' => $ip]);
    }

    public function clearLoginAttempts(string $email, string $ip): void
    {
        $sql = 'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip';
        $this->db->prepare($sql)->execute(['email' => $email, 'ip' => $ip]);
    }

    public function countRecentFailedAttempts(string $email, string $ip, int $windowMinutes): int
    {
        $sql = 'SELECT COUNT(*) FROM login_attempts
                WHERE email = :email AND ip_address = :ip
                AND attempted_at >= (NOW() - INTERVAL :window MINUTE)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':window', $windowMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
