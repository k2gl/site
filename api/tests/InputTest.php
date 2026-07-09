<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\HttpProblem;
use App\Http\Input;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(Input::class)]
#[CoversClass(HttpProblem::class)]
final class InputTest extends TestCase
{
    public function testDecodesAJsonObject(): void
    {
        fact(Input::fromString('{"envelope": "x", "n": 1}'))->is(['envelope' => 'x', 'n' => 1]);
    }

    public function testMalformedJsonIsRejected(): void
    {
        fact(static fn (): array => Input::fromString('{nope'))->throws(HttpProblem::class, 'not valid JSON');
    }

    public function testScalarBodyIsRejected(): void
    {
        fact(static fn (): array => Input::fromString('"just a string"'))->throws(HttpProblem::class, 'JSON object');
    }

    public function testAbsurdNestingIsRejected(): void
    {
        // arrange: depth beyond the decoder's 64-level cap
        $deep = str_repeat('[', 80) . '1' . str_repeat(']', 80);

        // act + assert
        fact(static fn (): array => Input::fromString($deep))->throws(HttpProblem::class);
    }
}
