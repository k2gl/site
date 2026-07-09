<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\HttpProblem;
use App\Packagist\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(Metadata::class)]
final class MetadataTest extends TestCase
{
    /** The p2 minified shape: later entries only carry what changed. */
    private const array MINIFIED = [
        [
            'version' => '1.2.0',
            'version_normalized' => '1.2.0.0',
            'dist' => ['url' => 'https://api.github.com/repos/k2gl/dsse/zipball/aaa', 'type' => 'zip'],
            'source' => ['url' => 'https://github.com/k2gl/dsse.git'],
            'license' => ['MIT'],
        ],
        [
            'version' => '1.1.0',
            'version_normalized' => '1.1.0.0',
            'dist' => ['url' => 'https://api.github.com/repos/k2gl/dsse/zipball/bbb', 'type' => 'zip'],
        ],
        [
            'version' => '1.1.0-RC1',
            'version_normalized' => '1.1.0.0-RC1',
            'license' => '__unset',
        ],
    ];

    public function testExpandReplaysDiffsAndUnsets(): void
    {
        // act
        $expanded = Metadata::expand(self::MINIFIED);

        // assert: inherited fields carry over
        fact($expanded[1]['source']['url'])->is('https://github.com/k2gl/dsse.git');
        fact($expanded[1]['license'])->is(['MIT']);
        fact($expanded[1]['dist']['url'])->is('https://api.github.com/repos/k2gl/dsse/zipball/bbb');

        // assert: __unset removes a key
        fact($expanded[2])->arrayNotHasKey('license');
    }

    public function testPickPrefersTheNewestStableVersion(): void
    {
        // arrange: a dev version on top, stable below
        $expanded = Metadata::expand([
            ['version' => '2.0.0-beta1', 'version_normalized' => '2.0.0.0-beta1'],
            ['version' => '1.2.0', 'version_normalized' => '1.2.0.0'],
        ]);

        // act + assert
        fact(Metadata::pick($expanded, null)['version'])->is('1.2.0');
    }

    public function testPickMatchesARequestedVersionIgnoringTheVPrefix(): void
    {
        $expanded = Metadata::expand(self::MINIFIED);

        fact(Metadata::pick($expanded, 'v1.1.0')['version'])->is('1.1.0');
    }

    public function testPickRejectsAnUnknownVersion(): void
    {
        $expanded = Metadata::expand(self::MINIFIED);

        fact(static fn (): array => Metadata::pick($expanded, '9.9.9'))
            ->throws(HttpProblem::class, 'No such version');
    }
}
