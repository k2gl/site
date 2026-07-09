<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    final protected static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/' . $name);
    }

    final protected static function fixturePath(string $name): string
    {
        return __DIR__ . '/../fixtures/' . $name;
    }
}
