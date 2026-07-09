<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\DsseInspect;
use App\Http\HttpProblem;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(DsseInspect::class)]
final class DsseInspectTest extends ApiTestCase
{
    public function testInspectsAndVerifiesTheFixtureEnvelope(): void
    {
        // arrange
        $endpoint = new DsseInspect;

        // act
        $response = $endpoint->handle([
            'envelope' => self::fixture('envelope-es256.json'),
            'publicKeyPem' => self::fixture('envelope-es256.pub.pem'),
        ]);

        // assert: decoded structure
        $result = $response['result'];
        fact($result['payloadType'])->is('application/vnd.in-toto+json');
        fact($result['payload']['json']['note'])->is('k2gl.com tools fixture');
        fact($result['pae']['preview'])->startsWith('DSSEv1 28 application/vnd.in-toto+json');

        // assert: the supplied key both matches the keyid and verifies
        fact($result['computedKeyId']['matchesEnvelopeKeyid'])->true();
        fact($result['verification'])->is(['attempted' => true, 'verified' => true]);
    }

    public function testAcceptsTheEnvelopeAsAnObjectToo(): void
    {
        // arrange
        $envelope = json_decode(self::fixture('envelope-es256.json'), associative: true);

        // act
        $response = new DsseInspect()->handle(['envelope' => $envelope]);

        // assert
        fact($response['result']['payloadType'])->is('application/vnd.in-toto+json');
        fact($response['result']['verification'])->is(['attempted' => false, 'reason' => 'no public key supplied']);
    }

    public function testWrongKeyFailsVerificationWithoutBeingAnError(): void
    {
        // arrange: a fresh key that never signed the fixture
        $stranger = openssl_pkey_get_details(
            openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']),
        )['key'];

        // act
        $response = new DsseInspect()->handle([
            'envelope' => self::fixture('envelope-es256.json'),
            'publicKeyPem' => $stranger,
        ]);

        // assert
        fact($response['result']['verification']['attempted'])->true();
        fact($response['result']['verification']['verified'])->false();
        fact($response['result']['computedKeyId']['matchesEnvelopeKeyid'])->false();
    }

    public function testGarbageEnvelopeIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new DsseInspect()->handle(['envelope' => '{"payload": 1}']))
            ->throws(HttpProblem::class);
    }

    public function testMissingEnvelopeIsRejected(): void
    {
        fact(static fn (): array => new DsseInspect()->handle([]))
            ->throws(HttpProblem::class, '"envelope" is required');
    }

    public function testBrokenPublicKeyIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new DsseInspect()->handle([
            'envelope' => self::fixture('envelope-es256.json'),
            'publicKeyPem' => 'not a pem',
        ]))->throws(HttpProblem::class, 'Public key rejected');
    }
}
