<?php

declare(strict_types=1);

namespace App\Endpoint;

final class Health
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'service' => 'k2gl-api',
            'php' => PHP_VERSION,
            'time' => gmdate('c'),
        ];
    }
}
