<?php

declare(strict_types=1);

/**
 * Extract each package's public API (classes + public method signatures) from
 * its src via PHP's tokenizer — no autoloading, no dependencies, no execution.
 * Output: src/data/api/{slug}.json, read by the content loader and projected into
 * the package page, its .md twin, and its .json feed.
 *
 * Usage: php tools/gen-api.php /path/to/packages [slug ...]
 */

$packagesDir = $argv[1] ?? null;
if ($packagesDir === null || ! is_dir($packagesDir)) {
    fwrite(STDERR, "usage: php tools/gen-api.php <packages-dir> [slug ...]\n");
    exit(1);
}

$slugs = array_slice($argv, 2);
if ($slugs === []) {
    $slugs = array_map('basename', glob("$packagesDir/*", GLOB_ONLYDIR) ?: []);
}

$outDir = __DIR__ . '/../src/data/api';
@mkdir($outDir, 0775, true);

foreach ($slugs as $slug) {
    $dir = "$packagesDir/$slug";
    if (! is_file("$dir/composer.json")) {
        continue;
    }
    $api = extractApi($dir);
    file_put_contents("$outDir/$slug.json", json_encode($api, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $methodCount = array_sum(array_map(fn (array $c): int => count($c['methods']), $api['classes']));
    echo "$slug: " . count($api['classes']) . " classes, $methodCount public methods\n";
}

/** @return array{classes: list<array{name: string, kind: string, methods: list<string>}>} */
function extractApi(string $packageDir): array
{
    $composer = json_decode((string) file_get_contents("$packageDir/composer.json"), true);
    $psr4 = $composer['autoload']['psr-4'] ?? [];

    $classes = [];
    foreach ($psr4 as $paths) {
        foreach ((array) $paths as $path) {
            $srcDir = rtrim("$packageDir/" . $path, '/');
            if (! is_dir($srcDir)) {
                continue;
            }
            foreach (phpFiles($srcDir) as $file) {
            foreach (parseFile((string) file_get_contents($file)) as $class) {
                // Skip internal-only API — agents want the supported surface.
                if (str_contains($class['name'], '\\Internal\\')) {
                    continue;
                }
                $classes[] = $class;
            }
            }
        }
    }
    usort($classes, fn (array $a, array $b): int => $a['name'] <=> $b['name']);

    return ['classes' => $classes];
}

/** @return list<string> */
function phpFiles(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = $f->getPathname();
        // A "" psr-4 path (package root) would otherwise sweep in dependencies.
        if (preg_match('#/(vendor|tests|Tests)/#', $p) === 1) {
            continue;
        }
        if ($f->getExtension() === 'php') {
            $files[] = $p;
        }
    }
    sort($files);

    return $files;
}

/** @return list<array{name: string, kind: string, methods: list<string>}> */
function parseFile(string $src): array
{
    $tokens = token_get_all($src);
    $n = count($tokens);
    $ns = '';
    $out = [];
    $class = null;
    $kind = 'class';
    $methods = [];
    $classDepth = null;
    $depth = 0;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_array($t)) {
            if ($t[0] === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $n && $tokens[$j] !== ';' && $tokens[$j] !== '{'; $j++) {
                    if (is_array($tokens[$j])) {
                        $ns .= $tokens[$j][1];
                    }
                }
                $ns = trim($ns);
            } elseif (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    if ($class !== null) {
                        $out[] = ['name' => $class, 'kind' => $kind, 'methods' => $methods];
                    }
                    $class = ($ns !== '' ? $ns . '\\' : '') . $tokens[$j][1];
                    $kind = $t[0] === T_INTERFACE ? 'interface' : ($t[0] === T_TRAIT ? 'trait' : ($t[0] === T_ENUM ? 'enum' : 'class'));
                    $methods = [];
                    $classDepth = $depth;
                }
            } elseif ($t[0] === T_FUNCTION && $class !== null) {
                if (visibility($tokens, $i) === 'public') {
                    $methods[] = signature($tokens, $i, $n);
                }
            }
        } elseif ($t === '{') {
            $depth++;
        } elseif ($t === '}') {
            $depth--;
            if ($class !== null && $classDepth !== null && $depth === $classDepth) {
                $out[] = ['name' => $class, 'kind' => $kind, 'methods' => $methods];
                $class = null;
                $methods = [];
                $classDepth = null;
            }
        }
    }
    if ($class !== null) {
        $out[] = ['name' => $class, 'kind' => $kind, 'methods' => $methods];
    }

    return $out;
}

/** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
function visibility(array $tokens, int $fnIndex): string
{
    for ($k = $fnIndex - 1; $k >= 0; $k--) {
        $tk = $tokens[$k];
        if (! is_array($tk)) {
            break;
        }
        if (in_array($tk[0], [T_WHITESPACE, T_ABSTRACT, T_FINAL, T_STATIC], true)) {
            continue;
        }
        if ($tk[0] === T_PRIVATE) {
            return 'private';
        }
        if ($tk[0] === T_PROTECTED) {
            return 'protected';
        }
        if ($tk[0] === T_PUBLIC) {
            return 'public';
        }
        break;
    }

    return 'public'; // no modifier => public
}

/** @param array<int, array{0:int,1:string,2:int}|string> $tokens */
function signature(array $tokens, int $fnIndex, int $n): string
{
    $sig = '';
    $parens = 0;
    $started = false;
    for ($j = $fnIndex; $j < $n; $j++) {
        $tj = $tokens[$j];
        $txt = is_array($tj) ? $tj[1] : $tj;
        if ($txt === '{') {
            break;
        }
        if ($txt === ';' && $parens === 0 && $started) {
            break;
        }
        if ($txt === '(') {
            $parens++;
            $started = true;
        }
        if ($txt === ')') {
            $parens--;
        }
        $sig .= $txt;
    }
    $sig = trim((string) preg_replace('/\s+/', ' ', $sig));

    return (string) preg_replace('/^function\s+/', '', $sig);
}
