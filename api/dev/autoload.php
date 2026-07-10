<?php

declare(strict_types=1);

/*
 * Composer-free autoloader for local development: composer's network installer
 * is unusable on the dev machine, so map the k2gl namespaces straight onto the
 * sibling package checkouts. Production images always use vendor/autoload.php.
 */

$packagesDir = getenv('K2GL_PACKAGES_DIR') ?: dirname(__DIR__, 3) . '/packages';

$prefixes = [
    'App\\' => [dirname(__DIR__) . '/src/'],
    'App\\Tests\\' => [dirname(__DIR__) . '/tests/'],
    'K2gl\\ComposerAttest\\' => [$packagesDir . '/composer-attest/src/'],
    'K2gl\\Dsse\\' => [$packagesDir . '/dsse/src/'],
    'K2gl\\InToto\\' => [$packagesDir . '/in-toto-attestation/src/'],
    'K2gl\\PHPUnitFluentAssertions\\' => [$packagesDir . '/phpunit-fluent-assertions/src/'],
    'K2gl\\SdJwt\\' => [$packagesDir . '/sd-jwt/src/'],
    'K2gl\\SdJwtVc\\' => [$packagesDir . '/sd-jwt-vc/src/'],
    'K2gl\\Sigstore\\' => [$packagesDir . '/sigstore-verify/src/'],
    'K2gl\\Slsa\\' => [$packagesDir . '/slsa-provenance/src/'],
    'K2gl\\Sshsig\\' => [$packagesDir . '/sshsig/src/'],
    'K2gl\\Tuf\\' => [$packagesDir . '/tuf/src/'],
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $dirs) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        foreach ($dirs as $dir) {
            if (is_file($dir . $relative)) {
                require $dir . $relative;

                return;
            }
        }
    }
});

// Third-party deps (phpseclib3, ...) come from a sibling's vendor/. Registered
// after the map above, so fresh sibling sources still win for K2gl\ classes.
$siblingVendor = $packagesDir . '/sigstore-verify/vendor/autoload.php';

if (is_file($siblingVendor)) {
    require_once $siblingVendor;
}

// fact() and friends normally arrive via composer's "files" autoload; the
// sibling vendor above may have brought its own copy already.
$aliases = $packagesDir . '/phpunit-fluent-assertions/src/aliases.php';

if (is_file($aliases) && ! function_exists('K2gl\PHPUnitFluentAssertions\fact')) {
    require_once $aliases;
}
