<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use K2gl\Dsse\Envelope;
use K2gl\Dsse\Exception\CryptoException;
use K2gl\Dsse\Exception\InvalidEnvelopeException;
use K2gl\Dsse\Exception\SignatureVerificationFailed;
use K2gl\Dsse\KeyId;
use K2gl\Dsse\PublicKey;
use K2gl\Dsse\Signature;
use K2gl\Dsse\Verifier;

final class DsseInspect
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $envelope = $this->envelope($input);

        [$verifier, $computedKeyId] = $this->key($input);

        $result = [
            'payloadType' => $envelope->payloadType,
            'payload' => $this->describePayload($envelope->payload),
            'signatures' => array_map(
                static fn (Signature $signature): array => [
                    'keyid' => $signature->keyId,
                    'sig' => base64_encode($signature->sig),
                ],
                $envelope->signatures,
            ),
            'pae' => $this->describePae($envelope->pae()),
        ];

        if ($computedKeyId !== null) {
            $envelopeKeyIds = array_map(static fn (Signature $s): ?string => $s->keyId, $envelope->signatures);
            $result['computedKeyId'] = [
                'value' => $computedKeyId,
                'matchesEnvelopeKeyid' => in_array($computedKeyId, $envelopeKeyIds, true),
            ];
        }

        $result['verification'] = $this->verify($envelope, $verifier);

        return ['result' => $result];
    }

    /** @param array<string, mixed> $input */
    private function envelope(array $input): Envelope
    {
        $raw = $input['envelope'] ?? null;

        try {
            if (is_string($raw)) {
                return Envelope::fromJson($raw);
            }

            if (is_array($raw)) {
                return Envelope::fromArray($raw);
            }
        } catch (InvalidEnvelopeException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        }

        throw new HttpProblem(status: 422, code: 'invalid_input', message: '"envelope" is required (a DSSE envelope object or its JSON string).');
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{0: ?Verifier, 1: ?string}
     */
    private function key(array $input): array
    {
        $pem = $input['publicKeyPem'] ?? null;
        $jwk = $input['publicKeyJwk'] ?? null;

        try {
            if (is_string($pem) && trim($pem) !== '') {
                return [PublicKey::fromPem($pem), KeyId::sha256Spki($pem)];
            }

            if (is_array($jwk) && $jwk !== []) {
                return [PublicKey::fromJwk($jwk), KeyId::jwkThumbprint($jwk)];
            }
        } catch (CryptoException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Public key rejected: ' . $e->getMessage());
        }

        return [null, null];
    }

    /** @return array<string, mixed> */
    private function verify(Envelope $envelope, ?Verifier $verifier): array
    {
        if ($verifier === null) {
            return ['attempted' => false, 'reason' => 'no public key supplied'];
        }

        try {
            $envelope->verify($verifier);
        } catch (SignatureVerificationFailed $e) {
            return ['attempted' => true, 'verified' => false, 'reason' => $e->getMessage()];
        }

        return ['attempted' => true, 'verified' => true];
    }

    /** @return array<string, mixed> */
    private function describePayload(string $payload): array
    {
        $described = ['size' => strlen($payload)];

        $json = json_decode($payload, associative: true);

        if (is_array($json)) {
            $described['json'] = $json;

            return $described;
        }

        if (mb_check_encoding($payload, 'UTF-8') && preg_match('/[^\P{C}\n\r\t]/u', $payload) !== 1) {
            $described['text'] = $payload;

            return $described;
        }

        $described['base64'] = base64_encode($payload);

        return $described;
    }

    /** @return array<string, mixed> */
    private function describePae(string $pae): array
    {
        $preview = substr($pae, 0, 512);

        return [
            'preview' => addcslashes($preview, "\x00..\x08\x0b\x0c\x0e..\x1f\x7f..\xff"),
            'truncated' => strlen($pae) > 512,
            'length' => strlen($pae),
        ];
    }
}
