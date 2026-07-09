<?php

declare(strict_types=1);

namespace App;

use K2gl\Sigstore\TrustedRoot;
use Throwable;

/**
 * The Sigstore public-good trusted root, fetched over TUF at most once a day.
 * Order: TRUSTED_ROOT_PATH pin -> day-fresh disk cache -> live TUF refresh ->
 * the snapshot committed in resources/ (marked so responses can say so).
 */
final class TrustedRootProvider
{
    private const int CACHE_TTL = 86_400;

    private static ?TrustedRoot $memo = null;

    private static string $source = 'unset';

    public static function get(): TrustedRoot
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $pinned = getenv('TRUSTED_ROOT_PATH');

        if (is_string($pinned) && $pinned !== '') {
            self::$source = 'pinned';

            return self::$memo = TrustedRoot::fromJson((string) file_get_contents($pinned));
        }

        $cacheFile = self::cacheFile();

        if (is_file($cacheFile) && filemtime($cacheFile) > time() - self::CACHE_TTL) {
            $cached = self::readCache($cacheFile);

            if ($cached !== null) {
                self::$source = 'tuf-cached';

                return self::$memo = $cached;
            }
        }

        try {
            $root = TrustedRoot::fromSigstorePublicGood();
        } catch (Throwable) {
            self::$source = 'bundled-fallback';

            return self::$memo = TrustedRoot::fromJson(
                (string) file_get_contents(dirname(__DIR__) . '/resources/trusted_root.json'),
            );
        }

        self::writeCache($cacheFile, $root);
        self::$source = 'tuf';

        return self::$memo = $root;
    }

    public static function source(): string
    {
        return self::$source;
    }

    /** Test hook: forget the memo so the next get() re-resolves. */
    public static function reset(): void
    {
        self::$memo = null;
        self::$source = 'unset';
    }

    private static function cacheFile(): string
    {
        return sys_get_temp_dir() . '/k2gl-api/trusted-root.ser';
    }

    private static function readCache(string $file): ?TrustedRoot
    {
        try {
            $root = unserialize((string) file_get_contents($file));
        } catch (Throwable) {
            return null;
        }

        return $root instanceof TrustedRoot ? $root : null;
    }

    private static function writeCache(string $file, TrustedRoot $root): void
    {
        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, serialize($root));
    }
}
