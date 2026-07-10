<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\SdJwtInspect;
use App\Endpoint\SdJwtIssue;
use App\Http\HttpProblem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(SdJwtIssue::class)]
final class SdJwtIssueTest extends TestCase
{
    public function testIssuedTokenRoundTripsThroughTheDebugger(): void
    {
        // act: issue with two disclosable claims
        $issued = new SdJwtIssue()->handle([
            'claims' => ['iss' => 'https://demo.k2gl.com', 'sub' => 'user_42', 'given_name' => 'John', 'plan' => 'pro'],
            'disclose' => ['given_name', 'plan'],
        ])['result'];

        // assert: shape of the issue response
        fact($issued['disclosures'])->is(2);
        fact($issued['issuerKey']['publicJwk']['kty'])->is('EC');
        fact($issued['note'])->containsString('throwaway');

        // act: feed the issued token + returned public key into the inspector
        $inspected = new SdJwtInspect()->handle([
            'sdJwt' => $issued['sdJwt'],
            'issuerKeyPem' => $issued['issuerKey']['publicPem'],
        ])['result'];

        // assert: verifies and recreates the hidden claims
        fact($inspected['verification']['verified'])->true();
        fact($inspected['recreatedClaims']['given_name'] ?? null)->is('John');
        fact($inspected['recreatedClaims']['plan'] ?? null)->is('pro');

        // assert: hidden claims left the signed payload itself
        fact((array) $inspected['payload'])->arrayNotHasKey('given_name');
    }

    public function testJwkVariantOfTheReturnedKeyVerifiesToo(): void
    {
        // arrange
        $issued = new SdJwtIssue()->handle([
            'claims' => ['iss' => 'https://demo.k2gl.com', 'email' => 'a@b.c'],
            'disclose' => ['email'],
        ])['result'];

        // act
        $inspected = new SdJwtInspect()->handle([
            'sdJwt' => $issued['sdJwt'],
            'issuerKeyJwk' => $issued['issuerKey']['publicJwk'],
        ])['result'];

        // assert
        fact($inspected['verification']['verified'])->true();
    }

    public function testUnknownDiscloseNameIsRejected(): void
    {
        fact(static fn (): array => new SdJwtIssue()->handle([
            'claims' => ['iss' => 'x'],
            'disclose' => ['nope'],
        ]))->throws(HttpProblem::class, 'Unknown claim');
    }

    public function testMissingClaimsAreRejected(): void
    {
        fact(static fn (): array => new SdJwtIssue()->handle(['claims' => []]))
            ->throws(HttpProblem::class, '"claims" is required');
    }
}
