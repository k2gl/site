<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Cache;
use App\Http\HttpClient;
use App\Http\HttpClientInterface;
use App\Http\HttpProblem;
use App\Packagist\Metadata;
use App\Sigstore\CertificateDetails;
use App\TrustedRootProvider;
use K2gl\ComposerAttest\AttestationVerifier;
use K2gl\ComposerAttest\Policy;
use K2gl\InToto\Statement;
use K2gl\Sigstore\Bundle;
use K2gl\Slsa\Provenance;
use Throwable;

/**
 * Does a Packagist package publish GitHub build-provenance attestations?
 * Mirrors what k2gl/composer-attest does at install time: resolve the release,
 * download its dist zip, hash it, ask GitHub for attestations on that digest and
 * verify the Sigstore bundle against the package's own repository identity.
 */
final class ComposerAttestations
{
    private const int MAX_ARTIFACT_BYTES = 67_108_864;

    private const int CACHE_TTL = 900;

    public function __construct(private readonly HttpClientInterface $http = new HttpClient) {}

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function handle(array $query): array
    {
        $package = $this->packageName($query);
        $requestedVersion = is_string($query['version'] ?? null) ? trim($query['version']) : null;

        $cacheKey = 'attestations:' . $package . '@' . ($requestedVersion ?: 'latest');
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return ['result' => ['cached' => true] + $cached];
        }

        $release = Metadata::pick(
            expanded: Metadata::fetch($this->http, $package),
            requested: $requestedVersion,
        );

        $result = $this->check(package: $package, release: $release);
        Cache::set(key: $cacheKey, value: $result, ttlSeconds: self::CACHE_TTL);

        return ['result' => ['cached' => false] + $result, 'meta' => ['trustedRoot' => TrustedRootProvider::source()]];
    }

    /** @param array<string, mixed> $query */
    private function packageName(array $query): string
    {
        $package = strtolower(trim((string) ($query['package'] ?? '')));

        if (preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#', $package) !== 1) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"package" must be a Packagist name like vendor/package.');
        }

        return $package;
    }

    /**
     * @param array<string, mixed> $release
     *
     * @return array<string, mixed>
     */
    private function check(string $package, array $release): array
    {
        $version = (string) ($release['version'] ?? 'unknown');
        $distUrl = is_array($release['dist'] ?? null) ? ($release['dist']['url'] ?? null) : null;
        $sourceUrl = is_array($release['source'] ?? null) ? ($release['source']['url'] ?? null) : null;

        $repo = $this->githubRepo($distUrl) ?? $this->githubRepo($sourceUrl);

        $result = [
            'package' => $package,
            'version' => $version,
            'source' => $repo,
        ];

        if ($repo === null) {
            $result['attestation'] = [
                'status' => 'unsupported_host',
                'message' => 'The release is not distributed from github.com, so GitHub attestations cannot apply.',
            ];

            return $result;
        }

        if (! is_string($distUrl) || $distUrl === '') {
            $result['attestation'] = ['status' => 'failed', 'message' => 'The release has no dist URL to download.'];

            return $result;
        }

        $artifact = $this->http->downloadToFile($distUrl, self::MAX_ARTIFACT_BYTES);

        try {
            $attestationsBody = null;

            $fetch = function (string $url) use (&$attestationsBody): ?string {
                $response = $this->http->get($url, ['Accept' => 'application/vnd.github+json']);

                if ($response['status'] === 404) {
                    return null;
                }

                if ($response['status'] !== 200) {
                    throw new HttpProblem(status: 502, code: 'upstream_error', message: 'GitHub responded with HTTP ' . $response['status'] . '.');
                }

                return $attestationsBody = $response['body'];
            };

            $verdict = new AttestationVerifier(
                fetch: $fetch,
                trustedRoot: TrustedRootProvider::get(),
                policy: Policy::fromExtra([]),
            )->verify($repo['owner'], $repo['repo'], $artifact['path']);

            $result['dist'] = ['url' => $distUrl, 'sha256' => $artifact['sha256'], 'sizeBytes' => $artifact['size']];
            $result['attestation'] = match (true) {
                $verdict->isVerified() => ['status' => 'verified', 'identity' => $verdict->message],
                $verdict->isFailure() => ['status' => 'failed', 'message' => $verdict->message],
                default => ['status' => 'no_attestation', 'message' => $verdict->message],
            };

            if ($attestationsBody !== null) {
                $provenance = $this->describeProvenance($attestationsBody);

                if ($provenance !== null) {
                    $result['provenance'] = $provenance;
                }
            }
        } finally {
            @unlink($artifact['path']);
        }

        return $result;
    }

    /** @return array{owner: string, repo: string}|null */
    private function githubRepo(mixed $url): ?array
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        foreach ([
            '#^https://api\.github\.com/repos/([^/]+)/([^/]+)/#',
            '#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?(?:/|$)#',
        ] as $pattern) {
            if (preg_match($pattern, $url, $m) === 1) {
                return ['owner' => $m[1], 'repo' => $m[2]];
            }
        }

        return null;
    }

    /**
     * Display-only enrichment from the first parseable attestation bundle: who
     * built it, from which workflow and commit. Verification already happened.
     *
     * @return array<string, mixed>|null
     */
    private function describeProvenance(string $attestationsBody): ?array
    {
        try {
            $decoded = json_decode($attestationsBody, associative: true);

            foreach (($decoded['attestations'] ?? []) as $attestation) {
                if (! is_array($attestation['bundle'] ?? null)) {
                    continue;
                }

                $bundle = Bundle::fromArray($attestation['bundle']);
                $described = [];

                if ($bundle->hasCertificate()) {
                    $certificate = CertificateDetails::fromDer((string) $bundle->leafCertificate);
                    $described['identity'] = CertificateDetails::firstSanValue($certificate);
                    $described['workflowRef'] = $certificate['fulcio']['githubWorkflowRef'] ?? null;
                    $described['commit'] = $certificate['fulcio']['githubWorkflowSha'] ?? null;
                }

                if ($bundle->isDsse() && $bundle->dsseEnvelope !== null) {
                    $statement = Statement::fromEnvelope($bundle->dsseEnvelope);
                    $described['predicateType'] = $statement->predicateType;

                    try {
                        $described['builderId'] = Provenance::fromStatement($statement)->runDetails->builder->id;
                    } catch (Throwable) {
                        // not SLSA provenance — the predicate type still tells the story
                    }
                }

                return $described === [] ? null : $described;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
