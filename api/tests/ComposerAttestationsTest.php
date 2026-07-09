<?php

declare(strict_types=1);

namespace App\Tests;

use App\Cache;
use App\Endpoint\ComposerAttestations;
use App\Http\HttpClientInterface;
use App\Http\HttpProblem;
use App\Tests\Support\ApiTestCase;
use App\TrustedRootProvider;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(ComposerAttestations::class)]
#[CoversClass(Cache::class)]
final class ComposerAttestationsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        putenv('TRUSTED_ROOT_PATH=' . self::fixturePath('trusted-root-public-good.json'));
        TrustedRootProvider::reset();
        Cache::purge();
    }

    protected function tearDown(): void
    {
        putenv('TRUSTED_ROOT_PATH');
        TrustedRootProvider::reset();
        Cache::purge();
    }

    public function testReportsNoAttestationForAGithubReleaseWithoutOne(): void
    {
        // arrange: Packagist metadata resolves, GitHub has no attestation (404)
        $endpoint = new ComposerAttestations($this->fakeHttp(attestationsStatus: 404));

        // act
        $response = $endpoint->handle(['package' => 'acme/widget']);

        // assert
        $result = $response['result'];
        fact($result['cached'])->false();
        fact($result['version'])->is('1.2.0');
        fact($result['source'])->is(['owner' => 'acme', 'repo' => 'widget']);
        fact($result['attestation']['status'])->is('no_attestation');

        // assert: the dist zip was really downloaded and hashed
        fact($result['dist']['sha256'])->is(hash('sha256', 'fake zip bytes'));
    }

    public function testSecondLookupComesFromTheCache(): void
    {
        // arrange
        $endpoint = new ComposerAttestations($this->fakeHttp(attestationsStatus: 404));

        // act
        $first = $endpoint->handle(['package' => 'acme/widget']);
        $second = $endpoint->handle(['package' => 'acme/widget']);

        // assert
        fact($first['result']['cached'])->false();
        fact($second['result']['cached'])->true();
        fact($second['result']['attestation']['status'])->is('no_attestation');
    }

    public function testNonGithubDistIsUnsupported(): void
    {
        // arrange
        $endpoint = new ComposerAttestations($this->fakeHttp(
            attestationsStatus: 404,
            distUrl: 'https://gitlab.com/acme/widget/-/archive/1.2.0.zip',
            sourceUrl: 'https://gitlab.com/acme/widget.git',
        ));

        // act
        $response = $endpoint->handle(['package' => 'acme/widget']);

        // assert: no download, no GitHub call — just an honest verdict
        fact($response['result']['attestation']['status'])->is('unsupported_host');
        fact($response['result'])->arrayNotHasKey('dist');
    }

    public function testMalformedPackageNameIsRejected(): void
    {
        $endpoint = new ComposerAttestations($this->fakeHttp(attestationsStatus: 404));

        fact(static fn (): array => $endpoint->handle(['package' => 'not a package']))
            ->throws(HttpProblem::class, 'vendor/package');
    }

    public function testUnknownPackageIsA404(): void
    {
        $endpoint = new ComposerAttestations($this->fakeHttp(attestationsStatus: 404, packagistStatus: 404));

        fact(static fn (): array => $endpoint->handle(['package' => 'acme/widget']))
            ->throws(HttpProblem::class, 'no package named');
    }

    private function fakeHttp(
        int $attestationsStatus,
        string $distUrl = 'https://api.github.com/repos/acme/widget/zipball/abc123',
        string $sourceUrl = 'https://github.com/acme/widget.git',
        int $packagistStatus = 200,
    ): HttpClientInterface {
        $p2 = json_encode(['packages' => ['acme/widget' => [[
            'version' => '1.2.0',
            'version_normalized' => '1.2.0.0',
            'dist' => ['url' => $distUrl, 'type' => 'zip'],
            'source' => ['url' => $sourceUrl],
        ]]]]);

        return new class ($p2, $packagistStatus, $attestationsStatus) implements HttpClientInterface {
            public function __construct(
                private readonly string $p2,
                private readonly int $packagistStatus,
                private readonly int $attestationsStatus,
            ) {}

            public function get(string $url, array $headers = []): array
            {
                if (str_contains($url, 'repo.packagist.org/p2/')) {
                    return ['status' => $this->packagistStatus, 'body' => $this->p2];
                }

                if (str_contains($url, '/attestations/sha256:')) {
                    return ['status' => $this->attestationsStatus, 'body' => ''];
                }

                return ['status' => 500, 'body' => 'unexpected url ' . $url];
            }

            public function downloadToFile(string $url, int $maxBytes): array
            {
                $path = (string) tempnam(sys_get_temp_dir(), 'k2gl-test-artifact-');
                file_put_contents($path, 'fake zip bytes');

                return ['path' => $path, 'size' => 14, 'sha256' => hash('sha256', 'fake zip bytes')];
            }
        };
    }
}
