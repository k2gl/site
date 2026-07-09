<?php

declare(strict_types=1);

namespace App\Packagist;

use App\Http\HttpClientInterface;
use App\Http\HttpProblem;

/**
 * Packagist p2 metadata: fetch, expand the minified version list, pick a release.
 * The p2 format stores each version as a diff against the previous entry, with
 * "__unset" removing a key — expansion just replays those diffs.
 */
final class Metadata
{
    /** @return list<array<string, mixed>> newest first, expanded */
    public static function fetch(HttpClientInterface $http, string $package): array
    {
        $response = $http->get('https://repo.packagist.org/p2/' . $package . '.json');

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
