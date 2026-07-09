<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpProblem;

/**
 * Per-IP token bucket in APCu. Without the extension (local dev) it lets
 * everything through — throttling is a production concern.
 */
final class RateLimiter
{
    public static function guard(string $bucket, int $capacity, float $refillPerMinute): void
    {
        if (! extension_loaded('apcu') || ! apcu_enabled()) {
            return;
        }

        $key = 'rl:' . $bucket . ':' . self::clientIp();
        $now = microtime(true);
        $state = apcu_fetch($key);

        if (! is_array($state)) {
            $state = ['tokens' => (float) $capacity, 'at' => $now];
        }

        $state['tokens'] = min((float) $capacity, $state['tokens'] + ($now - $state['at']) * $refillPerMinute / 60.0);
        $state['at'] = $now;

        if ($state['tokens'] < 1.0) {
            apcu_store($key, $state, 600);

            $retryAfter = (int) ceil((1.0 - $state['tokens']) * 60.0 / $refillPerMinute);

            throw new HttpProblem(
                status: 429,
                code: 'rate_limited',
                message: 'Too many requests — try again shortly.',
                headers: ['Retry-After' => (string) max($retryAfter, 1)],
            );
        }

        $state['tokens'] -= 1.0;
        apcu_store($key, $state, 600);
    }

    private static function clientIp(): string
    {
        // Behind our own Caddy the last X-Forwarded-For hop is the address it saw;
        // earlier hops are client-controlled and must not be trusted.
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwarded !== '') {
            $hops = explode(',', $forwarded);

            return trim(end($hops));
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
