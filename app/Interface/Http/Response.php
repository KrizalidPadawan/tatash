<?php
declare(strict_types=1);

namespace App\Interface\Http;

final class Response
{
    public function __construct(
        private readonly int $status,
        private readonly array $payload,
        private readonly array $headers = []
    ) {}

    public static function json(bool $success, array $data = [], array $errors = [], int $status = 200): self
    {
        return new self($status, [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }

        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
