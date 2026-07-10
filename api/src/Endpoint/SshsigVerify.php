<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use K2gl\Sshsig\AllowedSigners;
use K2gl\Sshsig\Exception\SshsigException;
use K2gl\Sshsig\SshSignature;
use K2gl\Sshsig\SshsigVerifier;

final class SshsigVerify
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $message = $input['message'] ?? null;
        $armored = $input['signature'] ?? null;

        if (! is_string($message)) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"message" is required (the signed data, verbatim).');
        }

        if (! is_string($armored) || trim($armored) === '') {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"signature" is required (the armored SSHSIG block).');
        }

        try {
            $signature = SshSignature::fromArmored($armored);
        } catch (SshsigException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        }

        $result = [
            'signature' => [
                'namespace' => $signature->namespace,
                'hashAlgorithm' => $signature->hashAlgorithm,
                'signatureAlgorithm' => $signature->signatureAlgorithm,
                'publicKeyFingerprint' => $signature->publicKey->fingerprint(),
            ],
            'verification' => $this->verify(input: $input, message: $message, armored: $armored, parsedNamespace: $signature->namespace),
        ];

        return ['result' => $result];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function verify(
        array $input,
        string $message,
        string $armored,
        string $parsedNamespace,
    ): array {
        $namespace = is_string($input['namespace'] ?? null) && $input['namespace'] !== ''
            ? $input['namespace']
            : $parsedNamespace;

        $allowedSigners = $input['allowedSigners'] ?? null;
        $identity = $input['identity'] ?? null;

        // Without an allowed_signers list only the signature itself can be checked
        // (the ssh-keygen -Y check-novalidate flow) — the signer stays unattributed.
        if (! is_string($allowedSigners) || trim($allowedSigners) === '') {
            try {
                new SshsigVerifier()->checkNoValidate(
                    message: $message,
                    armoredSignature: $armored,
                    namespace: $namespace,
                );
            } catch (SshsigException $e) {
                return ['attempted' => true, 'mode' => 'signature-only', 'verified' => false, 'reason' => $e->getMessage()];
            }

            return [
                'attempted' => true,
                'mode' => 'signature-only',
                'verified' => true,
                'note' => 'The signature is valid for this message, but without an allowed_signers list nothing ties the key to an identity.',
            ];
        }

        if (! is_string($identity) || $identity === '') {
            return [
                'attempted' => false,
                'reason' => 'an allowed_signers check needs the expected "identity" (the principal, e.g. an email)',
            ];
        }

        try {
            $verified = new SshsigVerifier()->verify(
                message: $message,
                armoredSignature: $armored,
                allowedSigners: AllowedSigners::fromString($allowedSigners),
                identity: $identity,
                namespace: $namespace,
            );
        } catch (SshsigException $e) {
            return ['attempted' => true, 'mode' => 'allowed-signers', 'verified' => false, 'reason' => $e->getMessage()];
        }

        return [
            'attempted' => true,
            'mode' => 'allowed-signers',
            'verified' => true,
            'identity' => $verified->identity,
            'principals' => $verified->principals,
            'namespace' => $verified->namespace,
        ];
    }
}
