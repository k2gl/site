<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpProblem;

/**
 * Per-IP token bucket on the file cache — one container, modest traffic, so a
 * filesystem bucket beats carrying a PECL extension just for counters.
 */
final class RateLimiter
{
    public static function guard(string $bucket, int $capacity, float $refillPerMinute): void
    {
        $key = 'rl:' . $bucket . ':' . self::clientIp();
        $now = microtime(true);
        $state = Cache::get($key);

        if (! is_array($state)) {
            $state = ['tokens' => (float) $capacity, 'at' => $now];
        }

        $state['tokens'] = min((float) $capacity, $state['tokens'] + ($now - $state['at']) * $refillPerMinute / 60.0);
        $state['at'] = $now;

        if ($state['tokens'] < 1.0) {
            Cache::set(key: $key, value: $state, ttlSeconds: 600);

            $retryAfter = (int) ceil((1.0 - $state['tokens']) * 60.0 / $refillPerMinute);

            throw new HttpProblem(
                status: 429,
                code: 'rate_limited',
                message: 'Too many requests — try again shortly.',
                headers: ['Retry-After' => (string) max($retryAfter, 1)],
            );
        }

        $state['tokens'] -= 1.0;
        Cache::set(key: $key, value: $state, ttlSeconds: 600);
    }

    private static function clientIp(): string
    {
        // Behind our own Caddy the last X-Forwarded-For hop is the address it saw;
        // earlier hops are client-controlled and must not be trusted.
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwarded !== '') {
            $hops = explode(',', $forwarded);

            return trim((string) end($hops));
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
