<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\HttpProblem;
use App\Http\JsonResponse;
use App\Http\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(Router::class)]
#[CoversClass(JsonResponse::class)]
#[CoversClass(HttpProblem::class)]
final class RouterTest extends TestCase
{
    public function testDispatchesTheMatchingRoute(): void
    {
        // arrange
        $router = new Router(['GET /api/health' => static fn (): JsonResponse => JsonResponse::ok(['service' => 'test'])]);

        // act
        $response = $router->dispatch('GET', '/api/health');

        // assert
        fact($response->status)->is(200);
        fact($response->body['ok'])->true();
        fact($response->body['service'])->is('test');
    }

    public function testUnknownPathIsA404(): void
    {
        $router = new Router([]);

        $response = $router->dispatch('GET', '/api/nope');

        fact($response->status)->is(404);
        fact($response->body['error']['code'])->is('not_found');
    }

    public function testWrongMethodIsA405WithAllowHeader(): void
    {
        // arrange
        $router = new Router(['POST /api/v1/dsse/inspect' => static fn (): JsonResponse => JsonResponse::ok([])]);

        // act
        $response = $router->dispatch('PUT', '/api/v1/dsse/inspect');

        // assert
        fact($response->status)->is(405);
        fact($response->body['error']['code'])->is('method_not_allowed');
        fact($response->headers['Allow'])->is('POST');
    }

    public function testHttpProblemBecomesItsStatusAndCode(): void
    {
        // arrange
        $router = new Router([
            'POST /boom' => static fn (): JsonResponse => throw new HttpProblem(
                status: 429,
                code: 'rate_limited',
                message: 'Slow down.',
                headers: ['Retry-After' => '7'],
            ),
        ]);

        // act
        $response = $router->dispatch('POST', '/boom');

        // assert
        fact($response->status)->is(429);
        fact($response->body['error']['code'])->is('rate_limited');
        fact($response->headers['Retry-After'])->is('7');
    }

    public function testUnexpectedThrowableIsAGeneric500(): void
    {
        // arrange: the router logs the exception — keep that off the test output
        $router = new Router(['GET /crash' => static fn (): JsonResponse => throw new RuntimeException('secret detail')]);
        $previousLog = ini_set('error_log', '/dev/null');

        // act
        try {
            $response = $router->dispatch('GET', '/crash');
        } finally {
            ini_set('error_log', (string) $previousLog);
        }

        // assert: internals never leak into the response
        fact($response->status)->is(500);
        fact($response->body['error']['code'])->is('internal');
        fact($response->body['error']['message'])->is('Unexpected server error.');
    }
}
