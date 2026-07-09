<?php

declare(strict_types=1);

namespace App\Http;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers = []): array;

    /**
     * Stream a URL to a temp file with a hard byte cap; the caller unlinks.
     *
     * @return array{path: string, size: int, sha256: string}
     */
    public function downloadToFile(string $url, int $maxBytes): array;
}
