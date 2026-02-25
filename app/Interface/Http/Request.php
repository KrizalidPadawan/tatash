<?php
declare(strict_types=1);

namespace App\Interface\Http;

final class Request
{
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly array $body,
        public readonly string $ip,
    ) {}

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $header = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$header] = $v;
            }
        }

        $input = file_get_contents('php://input') ?: '';
        $json = json_decode($input, true);
        $body = is_array($json) ? $json : $_POST;

        return new self(
            strtoupper($method),
            rtrim($path, '/') ?: '/',
            $_GET,
            $headers,
            $body,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        );
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }
}
