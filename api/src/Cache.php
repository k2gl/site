<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * A tiny file-backed TTL cache (JSON values). Filesystem beats APCu here: it
 * works in local dev and survives container restarts, and the values are small.
 */
final class Cache
{
    public static function get(string $key): mixed
    {
        try {
            $raw = @file_get_contents(self::path($key));

            if ($raw === false) {
                return null;
            }

            $entry = json_decode($raw, associative: true);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($entry) || ($entry['expires'] ?? 0) < time()) {
            return null;
        }

        return $entry['value'];
    }

    public static function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $path = self::path($key);
        @mkdir(dirname($path), 0777, true);
        @file_put_contents($path, json_encode(['expires' => time() + $ttlSeconds, 'value' => $value]));
    }

    /** Test hook: drop every cached entry. */
    public static function purge(): void
    {
        foreach (glob(sys_get_temp_dir() . '/k2gl-api/cache/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function path(string $key): string
    {
        return sys_get_temp_dir() . '/k2gl-api/cache/' . sha1($key) . '.json';
    }
}
