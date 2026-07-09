<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\SdJwtInspect;
use App\Http\HttpProblem;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(SdJwtInspect::class)]
final class SdJwtInspectTest extends ApiTestCase
{
    public function testInspectsAndVerifiesTheRfc9901IssuanceExample(): void
    {
        // arrange
        $issuerJwk = json_decode(self::fixture('rfc9901-issuer.jwk.json'), associative: true);

        // act
        $response = new SdJwtInspect()->handle([
            'sdJwt' => self::fixture('rfc9901-issuance.sd-jwt.txt'),
            'issuerKeyJwk' => $issuerJwk,
        ]);

        // assert: structure
        $result = $response['result'];
        fact($result['profile'])->is('plain');
        fact($result['sdAlg'])->is('sha-256');
        fact($result['keyBinding']['present'])->false();

        // assert: every disclosure decodes, digests back into the token, and is referenced
        fact(count($result['disclosures']) > 3)->true();

        foreach ($result['disclosures'] as $disclosure) {
            fact($disclosure['referenced'])->true();
        }

        // assert: recreated claims contain a disclosed value from the RFC example
        fact($result['recreatedClaims']['given_name'] ?? null)->is('John');

        // assert: the issuer signature verifies
        fact($result['verification']['attempted'])->true();
        fact($result['verification']['verified'])->true();
    }

    public function testDecodesAKeyBindingPresentationWithoutCheckingIt(): void
    {
        // act: no key — decode only
        $response = new SdJwtInspect()->handle(['sdJwt' => self::fixture('rfc9901-presentation-kb.sd-jwt.txt')]);

        // assert
        $result = $response['result'];
        fact($result['keyBinding']['present'])->true();
        fact($result['keyBinding']['checked'])->false();
        fact($result['keyBinding']['payload']->aud ?? null)->notNull();
        fact($result['verification'])->is(['attempted' => false, 'reason' => 'no issuer key supplied']);
    }

    public function testWrongIssuerKeyFailsVerificationWithoutBeingAnError(): void
    {
        // arrange: a fresh key that never signed the token
        $stranger = openssl_pkey_get_details(
            openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']),
        )['key'];

        // act
        $response = new SdJwtInspect()->handle([
            'sdJwt' => self::fixture('rfc9901-issuance.sd-jwt.txt'),
            'issuerKeyPem' => $stranger,
        ]);

        // assert
        fact($response['result']['verification']['attempted'])->true();
        fact($response['result']['verification']['verified'])->false();
    }

    public function testGarbageTokenIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new SdJwtInspect()->handle(['sdJwt' => 'not-a-jwt']))
            ->throws(HttpProblem::class);
    }

    public function testMissingTokenIsRejected(): void
    {
        fact(static fn (): array => new SdJwtInspect()->handle([]))
            ->throws(HttpProblem::class, '"sdJwt" is required');
    }
}
