<?php

declare(strict_types=1);

use App\Http\Router;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

// Local dev has no vendor/ (composer over the network is unusable on the dev
// machine) — fall back to a PSR-4 shim over the sibling package checkouts.
is_file($autoload) ? require $autoload : require dirname(__DIR__) . '/dev/autoload.php';

$routes = require dirname(__DIR__) . '/src/routes.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

new Router($routes)->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path)->send();
