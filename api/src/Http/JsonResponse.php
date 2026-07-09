<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly array $headers = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function ok(array $payload): self
    {
        return new self(status: 200, body: ['ok' => true] + $payload);
    }

    /** @param array<string, string> $headers */
    public static function fail(
        int $status,
        string $code,
        string $message,
        array $headers = [],
    ): self {
        return new self(
            status: $status,
            body: ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
            headers: $headers,
        );
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        // Substitute rather than fail on stray non-UTF-8 bytes (cert fields, raw
        // payload previews) — the response must always be valid JSON.
        echo json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
