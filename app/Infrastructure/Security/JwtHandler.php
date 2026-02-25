<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

final class JwtHandler
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
    ) {}

    public function issueAccessToken(array $claims, int $ttlSeconds): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'typ' => 'access',
        ]);

        return $this->encode($payload);
    }

    public function issueRefreshToken(array $claims, int $ttlSeconds): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'typ' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ]);

        return $this->encode($payload);
    }

    public function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid token format');
        }

        [$head, $payload, $sig] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $head . '.' . $payload, $this->secret, true));

        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('Invalid token signature');
        }

        $data = json_decode($this->base64UrlDecode($payload), true, 512, JSON_THROW_ON_ERROR);
        if (($data['exp'] ?? 0) < time()) {
            throw new \RuntimeException('Token expired');
        }

        return $data;
    }

    private function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $head = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $head . '.' . $body, $this->secret, true));

        return $head . '.' . $body . '.' . $sig;
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder > 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($input, '-_', '+/'), true);
    }
}
