<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use K2gl\Dsse\Exception\CryptoException;
use K2gl\Dsse\PublicKey;
use K2gl\Dsse\Verifier;
use K2gl\SdJwt\Disclosure;
use K2gl\SdJwt\Exception\InvalidSdJwtException;
use K2gl\SdJwt\Exception\SdJwtException;
use K2gl\SdJwt\Presentation;
use K2gl\SdJwt\SdJwt;
use K2gl\SdJwt\SdJwtVerifier;
use K2gl\SdJwtVc\Exception\SdJwtVcException;
use K2gl\SdJwtVc\SdJwtVcVerifier;
use K2gl\SdJwtVc\VerifiedSdJwtVc;
use Throwable;

final class SdJwtInspect
{
    private const array VC_TYPES = ['dc+sd-jwt', 'vc+sd-jwt'];

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $sdJwt = $this->parse($input);

        $header = $sdJwt->header();
        $payload = $sdJwt->payload();
        $sdAlg = is_string($payload->_sd_alg ?? null) ? $payload->_sd_alg : 'sha-256';
        $profile = $this->profile(input: $input, typ: $header->typ ?? null);

        [$verifier, $keySupplied] = $this->key($input);

        $result = [
            'profile' => $profile,
            'header' => $header,
            'payload' => $payload,
            'sdAlg' => $sdAlg,
            'disclosures' => $this->describeDisclosures($sdJwt, $sdAlg),
            'recreatedClaims' => $this->recreatedClaims($sdJwt),
            'keyBinding' => $this->describeKeyBinding($sdJwt),
            'verification' => $this->verify(
                sdJwt: $sdJwt,
                profile: $profile,
                verifier: $verifier,
                keySupplied: $keySupplied,
            ),
        ];

        return ['result' => $result];
    }

    /** @param array<string, mixed> $input */
    private function parse(array $input): SdJwt
    {
        $compact = $input['sdJwt'] ?? null;

        if (! is_string($compact) || trim($compact) === '') {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"sdJwt" is required (the compact SD-JWT string).');
        }

        try {
            return SdJwt::parse(trim($compact));
        } catch (InvalidSdJwtException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        }
    }

    /** @param array<string, mixed> $input */
    private function profile(array $input, mixed $typ): string
    {
        $requested = $input['profile'] ?? 'auto';

        if ($requested === 'plain' || $requested === 'vc') {
            return $requested;
        }

        return is_string($typ) && in_array(strtolower($typ), self::VC_TYPES, true) ? 'vc' : 'plain';
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{0: ?Verifier, 1: bool}
     */
    private function key(array $input): array
    {
        $pem = $input['issuerKeyPem'] ?? null;
        $jwk = $input['issuerKeyJwk'] ?? null;

        try {
            if (is_string($pem) && trim($pem) !== '') {
                return [PublicKey::fromPem($pem), true];
            }

            if (is_array($jwk) && $jwk !== []) {
                return [PublicKey::fromJwk($jwk), true];
            }
        } catch (CryptoException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Issuer key rejected: ' . $e->getMessage());
        }

        return [null, false];
    }

    /** @return list<array<string, mixed>> */
    private function describeDisclosures(SdJwt $sdJwt, string $sdAlg): array
    {
        $referenced = [];
        $this->collectDigests($sdJwt->payload(), $referenced);

        $described = [];

        foreach ($sdJwt->disclosures as $disclosure) {
            $this->collectDigests($disclosure->value, $referenced);
        }

        foreach ($sdJwt->disclosures as $disclosure) {
            $digest = null;

            try {
                $digest = $disclosure->digest($sdAlg);
            } catch (SdJwtException) {
                // unsupported hash — still show the decoded disclosure
            }

            $described[] = [
                'encoded' => $disclosure->encoded,
                'salt' => $disclosure->salt,
                'kind' => $disclosure->isArrayElement() ? 'arrayElement' : 'property',
                'claimName' => $disclosure->claimName,
                'value' => $disclosure->value,
                'digest' => $digest,
                'referenced' => $digest !== null && in_array($digest, $referenced, true),
            ];
        }

        return $described;
    }

    /**
     * Digests are referenced from "_sd" arrays and "..." array-element markers,
     * both in the payload and nested inside other disclosures' values.
     *
     * @param list<string> $out
     */
    private function collectDigests(mixed $node, array &$out): void
    {
        if (is_object($node)) {
            $node = (array) $node;
        }

        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key === '_sd' && is_array($value)) {
                foreach ($value as $digest) {
                    if (is_string($digest)) {
                        $out[] = $digest;
                    }
                }

                continue;
            }

            if ($key === '...' && is_string($value)) {
                $out[] = $value;

                continue;
            }

            $this->collectDigests($value, $out);
        }
    }

    /** @return array<string, mixed> */
    private function recreatedClaims(SdJwt $sdJwt): array
    {
        try {
            return Presentation::of($sdJwt->withoutKeyBinding())->claims();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function describeKeyBinding(SdJwt $sdJwt): array
    {
        if (! $sdJwt->hasKeyBinding()) {
            return ['present' => false];
        }

        $described = ['present' => true, 'checked' => false];
        $parts = explode('.', (string) $sdJwt->keyBindingJwt);

        foreach (['header' => 0, 'payload' => 1] as $name => $index) {
            $decoded = json_decode(
                (string) base64_decode(strtr($parts[$index] ?? '', '-_', '+/'), strict: false),
                associative: false,
            );

            if ($decoded !== null) {
                $described[$name] = $decoded;
            }
        }

        return $described;
    }

    /** @return array<string, mixed> */
    private function verify(
        SdJwt $sdJwt,
        string $profile,
        ?Verifier $verifier,
        bool $keySupplied,
    ): array {
        if (! $keySupplied || $verifier === null) {
            return ['attempted' => false, 'reason' => 'no issuer key supplied'];
        }

        $issuerSigned = $sdJwt->hasKeyBinding() ? $sdJwt->withoutKeyBinding() : $sdJwt;

        try {
            if ($profile === 'vc') {
                $verified = new SdJwtVcVerifier(acceptLegacyType: true)->verify($issuerSigned, $verifier);

                return [
                    'attempted' => true,
                    'verified' => true,
                    'vc' => $this->describeVc($verified),
                ];
            }

            new SdJwtVerifier()->verify($issuerSigned, $verifier);
        } catch (SdJwtException | SdJwtVcException $e) {
            return ['attempted' => true, 'verified' => false, 'reason' => $e->getMessage()];
        }

        return ['attempted' => true, 'verified' => true];
    }

    /** @return array<string, mixed> */
    private function describeVc(VerifiedSdJwtVc $verified): array
    {
        $vc = ['vct' => $verified->vct(), 'issuer' => $verified->issuer()];

        try {
            $vc['status'] = $verified->status();
        } catch (Throwable) {
            // status is optional in the credential
        }

        return $vc;
    }
}
