<?php

declare(strict_types=1);

namespace App\Packagist;

use App\Http\HttpClientInterface;
use App\Http\HttpProblem;

/**
 * Packagist p2 metadata: fetch, expand the minified version list, pick a release.
 * The p2 format stores each version as a diff against the previous entry, with
 * "__unset" removing a key — expansion just replays those diffs.
 *
 * When repo.packagist.org cannot be reached (from the VPS a share of SYNs
 * towards its CDN goes unanswered, see HttpClient) the same p2 file is read from
 * a mirror. Mirrors rewrite dist URLs to their own storage, so the GitHub dist
 * and source are rebuilt from the commit reference and the repository link;
 * the artifact itself is still downloaded from GitHub and verified against
 * GitHub's attestations, so the mirror only ever influences which release is
 * looked at — and the result says it was used.
 */
final class Metadata
{
    public const string SOURCE_PACKAGIST = 'packagist';

    public const string SOURCE_MIRROR = 'mirror';

    private const string PACKAGIST = 'https://repo.packagist.org/p2/';

    private const string MIRROR = 'https://mirrors.cloud.tencent.com/composer/p2/';

    /** @return array{source: string, versions: list<array<string, mixed>>} versions newest first, expanded */
    public static function fetch(HttpClientInterface $http, string $package): array
    {
        try {
            return ['source' => self::SOURCE_PACKAGIST, 'versions' => self::read($http, self::PACKAGIST, $package)];
        } catch (HttpProblem $problem) {
            if ($problem->status !== 502) {
                throw $problem;
            }

            try {
                $versions = self::read($http, self::MIRROR, $package);
            } catch (HttpProblem) {
                throw $problem; // the mirror is a fallback, not a second opinion
            }

            return ['source' => self::SOURCE_MIRROR, 'versions' => array_map(self::restoreGithubUrls(...), $versions)];
        }
    }

    /** @return list<array<string, mixed>> */
    private static function read(HttpClientInterface $http, string $base, string $package): array
    {
        $response = $http->get($base . $package . '.json');

        if ($response['status'] === 404) {
            throw new HttpProblem(status: 404, code: 'unknown_package', message: 'Packagist has no package named "' . $package . '".');
        }

        if ($response['status'] !== 200) {
            throw new HttpProblem(status: 502, code: 'upstream_error', message: 'Packagist responded with HTTP ' . $response['status'] . '.');
        }

        $decoded = json_decode($response['body'], associative: true);
        $versions = $decoded['packages'][$package] ?? null;

        if (! is_array($versions) || $versions === []) {
            throw new HttpProblem(status: 502, code: 'upstream_error', message: 'Packagist metadata for "' . $package . '" is empty.');
        }

        return self::expand($versions);
    }

    /**
     * Mirrors drop "source" and point "dist" at themselves; the commit reference
     * and the repository link survive, which is enough to rebuild both.
     *
     * @param array<string, mixed> $version
     *
     * @return array<string, mixed>
     */
    public static function restoreGithubUrls(array $version): array
    {
        $reference = is_array($version['dist'] ?? null) ? ($version['dist']['reference'] ?? null) : null;
        $repository = null;

        foreach ([$version['support']['source'] ?? null, $version['homepage'] ?? null] as $candidate) {
            if (is_string($candidate) && preg_match('#^https://github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$#', $candidate, $m) === 1) {
                $repository = $m[1] . '/' . $m[2];

                break;
            }
        }

        if (! is_string($reference) || $reference === '' || $repository === null) {
            return $version;
        }

        $version['source'] = ['url' => 'https://github.com/' . $repository . '.git', 'type' => 'git', 'reference' => $reference];
        $version['dist'] = ['url' => 'https://api.github.com/repos/' . $repository . '/zipball/' . $reference, 'type' => 'zip', 'reference' => $reference];

        return $version;
    }

    /**
     * @param list<array<string, mixed>> $versions minified, newest first
     *
     * @return list<array<string, mixed>>
     */
    public static function expand(array $versions): array
    {
        $expanded = [];
        $carry = [];

        foreach ($versions as $diff) {
            foreach ($diff as $key => $value) {
                if ($value === '__unset') {
                    unset($carry[$key]);
                } else {
                    $carry[$key] = $value;
                }
            }

            $expanded[] = $carry;
        }

        return $expanded;
    }

    /**
     * The requested version, or the newest stable one (newest anything as a
     * last resort).
     *
     * @param list<array<string, mixed>> $expanded
     *
     * @return array<string, mixed>
     */
    public static function pick(array $expanded, ?string $requested): array
    {
        if ($requested !== null && $requested !== '') {
            foreach ($expanded as $version) {
                if (ltrim((string) ($version['version'] ?? ''), 'v') === ltrim($requested, 'v')) {
                    return $version;
                }
            }

            throw new HttpProblem(status: 422, code: 'unknown_version', message: 'No such version: ' . $requested . '.');
        }

        foreach ($expanded as $version) {
            if (! str_contains((string) ($version['version_normalized'] ?? ''), '-')) {
                return $version;
            }
        }

        return $expanded[0];
    }
}
