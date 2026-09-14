<?php

declare(strict_types=1);

/*
 * Pre-check packages so their verdicts sit in the cache before anyone asks:
 *
 *   php bin/warm-cache.php k2gl/dsse k2gl/sshsig ...
 *
 * The deploy runs this for the whole catalog right after the containers come up.
 * It goes through the endpoint class rather than HTTP, so the per-IP rate limit
 * does not apply; a package that fails (Packagist unreachable, see HttpClient)
 * is reported and skipped — the next request or the next deploy will retry.
 */

use App\Endpoint\ComposerAttestations;
use App\Http\HttpProblem;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
is_file($autoload) ? require $autoload : require dirname(__DIR__) . '/dev/autoload.php';

$packages = array_slice($argv, 1);

if ($packages === []) {
    fwrite(STDERR, "usage: php bin/warm-cache.php vendor/package ...\n");
    exit(64);
}

$endpoint = new ComposerAttestations;
$failed = 0;

foreach ($packages as $package) {
    $started = microtime(true);

    try {
        $result = $endpoint->handle(['package' => $package])['result'];
        $status = (string) ($result['attestation']['status'] ?? 'unknown');
        $freshness = ($result['stale'] ?? false) ? 'stale' : (($result['cached'] ?? false) ? 'cached' : 'fresh');
        printf("%-40s %-18s %-7s %5.1fs\n", $package, $status, $freshness, microtime(true) - $started);
    } catch (HttpProblem $problem) {
        $failed++;
        printf("%-40s FAILED %d %s\n", $package, $problem->status, $problem->getMessage());
    }
}

printf("warmed %d/%d\n", count($packages) - $failed, count($packages));
exit($failed === 0 ? 0 : 1);
