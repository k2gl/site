<?php

declare(strict_types=1);

namespace App\Tests;

use App\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase
{
    public function testLetsRequestsThroughWithoutApcu(): void
    {
        if (extension_loaded('apcu') && apcu_enabled()) {
            self::markTestSkipped('APCu is active here; this covers the degraded path.');
        }

        // act: would throw after the bucket drained if throttling were active
        foreach (range(1, 50) as $ignored) {
            RateLimiter::guard(bucket: 'test', capacity: 1, refillPerMinute: 1.0);
        }

        // assert: reaching this point IS the behavior under test
        fact(true)->true();
    }
}
