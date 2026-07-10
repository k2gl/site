<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\SshsigVerify;
use App\Http\HttpProblem;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(SshsigVerify::class)]
final class SshsigVerifyTest extends ApiTestCase
{
    public function testVerifiesAgainstAllowedSigners(): void
    {
        // act
        $response = new SshsigVerify()->handle([
            'message' => self::fixture('sshsig/message.txt'),
            'signature' => self::fixture('sshsig/ed25519.sig'),
            'allowedSigners' => self::fixture('sshsig/allowed_signers'),
            'identity' => 'alice@example.com',
            'namespace' => 'file',
        ]);

        // assert: parsed surface
        $result = $response['result'];
        fact($result['signature']['signatureAlgorithm'])->is('ssh-ed25519');
        fact($result['signature']['namespace'])->is('file');
        fact($result['signature']['publicKeyFingerprint'])->startsWith('SHA256:');

        // assert: full ssh-keygen -Y verify semantics
        fact($result['verification']['mode'])->is('allowed-signers');
        fact($result['verification']['verified'])->true();
        fact($result['verification']['identity'])->is('alice@example.com');
    }

    public function testSignatureOnlyModeWithoutAllowedSigners(): void
    {
        // act: no allowed_signers — check-novalidate semantics
        $response = new SshsigVerify()->handle([
            'message' => self::fixture('sshsig/message.txt'),
            'signature' => self::fixture('sshsig/ecdsa-nistp256.sig'),
        ]);

        // assert
        fact($response['result']['verification']['mode'])->is('signature-only');
        fact($response['result']['verification']['verified'])->true();
        fact($response['result']['verification']['note'])->containsString('allowed_signers');
    }

    public function testForeignIdentityFailsWithoutBeingAnError(): void
    {
        // act
        $response = new SshsigVerify()->handle([
            'message' => self::fixture('sshsig/message.txt'),
            'signature' => self::fixture('sshsig/ed25519.sig'),
            'allowedSigners' => self::fixture('sshsig/allowed_signers'),
            'identity' => 'mallory@example.com',
        ]);

        // assert
        fact($response['result']['verification']['verified'])->false();
    }

    public function testTamperedMessageFailsVerification(): void
    {
        $response = new SshsigVerify()->handle([
            'message' => self::fixture('sshsig/message.txt') . 'tampered',
            'signature' => self::fixture('sshsig/ed25519.sig'),
        ]);

        fact($response['result']['verification']['verified'])->false();
    }

    public function testAllowedSignersWithoutIdentityIsNotAttempted(): void
    {
        $response = new SshsigVerify()->handle([
            'message' => self::fixture('sshsig/message.txt'),
            'signature' => self::fixture('sshsig/ed25519.sig'),
            'allowedSigners' => self::fixture('sshsig/allowed_signers'),
        ]);

        fact($response['result']['verification']['attempted'])->false();
        fact($response['result']['verification']['reason'])->containsString('identity');
    }

    public function testGarbageSignatureIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new SshsigVerify()->handle([
            'message' => 'hello',
            'signature' => 'not an sshsig block',
        ]))->throws(HttpProblem::class);
    }
}
