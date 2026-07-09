<?php

declare(strict_types=1);

namespace App\Http;

use Closure;
use Throwable;

final class Router
{
    /** @param array<string, Closure(): JsonResponse> $routes keyed "METHOD /path" */
    public function __construct(private readonly array $routes) {}

    public function dispatch(string $method, string $path): JsonResponse
    {
        $handler = $this->routes[strtoupper($method) . ' ' . $path] ?? null;

        if ($handler === null) {
            return $this->miss($path);
        }

        try {
            return $handler();
        } catch (HttpProblem $problem) {
            return JsonResponse::fail(
                status: $problem->status,
                code: $problem->errorCode,
                message: $problem->getMessage(),
                headers: $problem->headers,
            );
        } catch (Throwable $e) {
            // Never leak internals; details stay in the container's stderr only.
            error_log('k2gl-api: ' . $e);

            return JsonResponse::fail(status: 500, code: 'internal', message: 'Unexpected server error.');
        }
    }

    private function miss(string $path): JsonResponse
    {
        $allowed = [];

        foreach (array_keys($this->routes) as $route) {
            [$routeMethod, $routePath] = explode(' ', $route, 2);

            if ($routePath === $path) {
                $allowed[] = $routeMethod;
            }
        }

        if ($allowed !== []) {
            return JsonResponse::fail(
                status: 405,
                code: 'method_not_allowed',
                message: 'Use ' . implode(', ', $allowed) . ' for this endpoint.',
                headers: ['Allow' => implode(', ', $allowed)],
            );
        }

        return JsonResponse::fail(status: 404, code: 'not_found', message: 'Unknown endpoint.');
    }
}
