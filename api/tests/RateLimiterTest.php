<?php

declare(strict_types=1);

namespace App\Tests;

use App\Cache;
use App\Http\HttpProblem;
use App\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(RateLimiter::class)]
#[CoversClass(Cache::class)]
final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        Cache::purge();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        Cache::purge();
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testThrottlesOnceTheBucketDrains(): void
    {
        // act: the two budgeted requests pass
        RateLimiter::guard(bucket: 'test', capacity: 2, refillPerMinute: 60.0);
        RateLimiter::guard(bucket: 'test', capacity: 2, refillPerMinute: 60.0);

        // assert: the third is a 429 with a retry hint
        fact(static fn () => RateLimiter::guard(bucket: 'test', capacity: 2, refillPerMinute: 60.0))
            ->throws(HttpProblem::class, 'Too many requests');
    }

    public function testBucketsAreKeyedByClientIp(): void
    {
        // arrange: drain one client
        RateLimiter::guard(bucket: 'test', capacity: 1, refillPerMinute: 1.0);

        // act: a different client arrives via the proxy header
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.99';
        RateLimiter::guard(bucket: 'test', capacity: 1, refillPerMinute: 1.0);

        // assert: the proxied client now has its own drained bucket (keyed by the
        // last hop, the only one our Caddy vouches for)
        fact(static fn () => RateLimiter::guard(bucket: 'test', capacity: 1, refillPerMinute: 1.0))
            ->throws(HttpProblem::class);
    }
}
