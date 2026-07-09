<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;

final class Input
{
    public const int MAX_BYTES = 1_048_576;

    /** @return array<string, mixed> */
    public static function json(): array
    {
        return self::fromString(self::readBody());
    }

    /** @return array<string, mixed> */
    public static function fromString(string $body): array
    {
        try {
            $decoded = json_decode($body, associative: true, depth: 64, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpProblem(status: 400, code: 'invalid_json', message: 'Request body is not valid JSON: ' . $e->getMessage());
        }

        if (! is_array($decoded)) {
            throw new HttpProblem(status: 400, code: 'invalid_json', message: 'Request body must be a JSON object.');
        }

        return $decoded;
    }

    private static function readBody(): string
    {
        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            throw new HttpProblem(status: 400, code: 'invalid_json', message: 'Request body could not be read.');
        }

        $body = '';

        while (! feof($stream)) {
            $chunk = fread($stream, 65_536);

            if ($chunk === false) {
                break;
            }

            $body .= $chunk;

            if (strlen($body) > self::MAX_BYTES) {
                fclose($stream);

                throw new HttpProblem(status: 413, code: 'too_large', message: 'Request body exceeds 1 MiB.');
            }
        }

        fclose($stream);

        return $body;
    }
}
