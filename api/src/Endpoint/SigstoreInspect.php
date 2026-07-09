<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use App\Sigstore\CertificateDetails;
use App\TrustedRootProvider;
use K2gl\InToto\Statement;
use K2gl\Sigstore\Bundle;
use K2gl\Sigstore\Exception\InvalidBundleException;
use K2gl\Sigstore\Exception\SigstoreException;
use K2gl\Sigstore\Exception\UnsupportedBundleException;
use K2gl\Sigstore\IdentityPolicy;
use K2gl\Sigstore\SigstoreVerifier;
use K2gl\Sigstore\SubjectPolicy;
use K2gl\Sigstore\TlogEntry;
use Throwable;

final class SigstoreInspect
{
    private const string GITHUB_ACTIONS_ISSUER = 'https://token.actions.githubusercontent.com';

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $bundle = $this->bundle($input);
        $digest = $this->artifactSha256($input);

        $certificate = $bundle->hasCertificate()
            ? CertificateDetails::fromDer((string) $bundle->leafCertificate)
            : null;

        $result = ['mediaType' => $bundle->mediaType];

        if ($certificate !== null) {
            $result['certificate'] = $certificate;
        }

        $result['tlog'] = array_map(
            static fn (TlogEntry $entry): array => [
                'logIndex' => $entry->logIndex,
                'logId' => bin2hex($entry->logId),
                'kind' => $entry->kind,
                'integratedTime' => $entry->integratedTime !== null ? gmdate('c', $entry->integratedTime) : null,
                'hasInclusionProof' => $entry->inclusionProof !== null,
                'hasInclusionPromise' => $entry->signedEntryTimestamp !== null,
            ],
            $bundle->tlogEntries,
        );

        if ($bundle->isDsse() && $bundle->dsseEnvelope !== null) {
            $result['dsse'] = $this->describeDsse($bundle);
        }

        if ($bundle->isMessageSignature() && $bundle->messageSignature !== null) {
            $result['messageSignature'] = [
                'hashAlgorithm' => $bundle->messageSignature->hashAlgorithm,
                'digest' => bin2hex($bundle->messageSignature->messageDigest),
            ];
        }

        $result['verification'] = $this->verify(
            bundle: $bundle,
            certificate: $certificate,
            digest: $digest,
            input: $input,
        );

        $meta = TrustedRootProvider::source() === 'unset'
            ? []
            : ['trustedRoot' => TrustedRootProvider::source()];

        return ['result' => $result, 'meta' => $meta];
    }

    /** @param array<string, mixed> $input */
    private function bundle(array $input): Bundle
    {
        $raw = $input['bundle'] ?? null;

        try {
            if (is_string($raw)) {
                return Bundle::fromJson($raw);
            }

            if (is_array($raw)) {
                return Bundle::fromArray($raw);
            }
        } catch (InvalidBundleException | UnsupportedBundleException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        }

        throw new HttpProblem(status: 422, code: 'invalid_input', message: '"bundle" is required (a Sigstore bundle object or its JSON string).');
    }

    /** @param array<string, mixed> $input */
    private function artifactSha256(array $input): ?string
    {
        $digest = $input['artifactSha256'] ?? null;

        if ($digest === null || $digest === '') {
            return null;
        }

        if (! is_string($digest) || preg_match('/^[0-9a-f]{64}$/i', $digest) !== 1) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"artifactSha256" must be 64 hex characters.');
        }

        return strtolower($digest);
    }

    /** @return array<string, mixed> */
    private function describeDsse(Bundle $bundle): array
    {
        $envelope = $bundle->dsseEnvelope;
        $described = ['payloadType' => $envelope->payloadType];

        try {
            $statement = Statement::fromEnvelope($envelope);
            $described['statement'] = [
                'predicateType' => $statement->predicateType,
                'subjects' => array_map(
                    static fn (object $subject): array => [
                        'name' => $subject->name,
                        'digest' => $subject->digest,
                    ],
                    $statement->subject,
                ),
            ];
        } catch (Throwable) {
            $payload = json_decode($envelope->payload, associative: true);

            if (is_array($payload)) {
                $described['payloadJson'] = $payload;
            }
        }

        return $described;
    }

    /**
     * @param array<string, mixed>|null $certificate
     * @param array<string, mixed>      $input
     *
     * @return array<string, mixed>
     */
    private function verify(
        Bundle $bundle,
        ?array $certificate,
        ?string $digest,
        array $input,
    ): array {
        if ($bundle->isPublicKey()) {
            return $this->verifyWithPublicKey(
                bundle: $bundle,
                digest: $digest,
                publicKeyPem: is_string($input['publicKeyPem'] ?? null) ? $input['publicKeyPem'] : null,
            );
        }

        $identity = $this->identityPolicy(certificate: $certificate, input: $input);

        if (! $identity['policy'] instanceof IdentityPolicy) {
            return ['attempted' => false, 'reason' => $identity['reason']];
        }

        if ($bundle->isMessageSignature() && $digest === null) {
            return [
                'attempted' => false,
                'reason' => 'messageSignature bundles verify against an artifact — supply artifactSha256',
            ];
        }

        try {
            if ($bundle->isMessageSignature()) {
                new SigstoreVerifier()->verifyArtifactDigest(
                    bundle: $bundle,
                    algorithm: 'sha256',
                    hexDigest: (string) $digest,
                    trustedRoot: TrustedRootProvider::get(),
                    identityPolicy: $identity['policy'],
                );
            } else {
                new SigstoreVerifier()->verify(
                    bundle: $bundle,
                    trustedRoot: TrustedRootProvider::get(),
                    identityPolicy: $identity['policy'],
                    subjectPolicy: $digest !== null ? new SubjectPolicy(algorithm: 'sha256', hexDigest: $digest) : null,
                );
            }
        } catch (SigstoreException $e) {
            return [
                'attempted' => true,
                'verified' => false,
                'identity' => $identity['description'],
                'reason' => $e->getMessage(),
            ];
        }

        return [
            'attempted' => true,
            'verified' => true,
            'identity' => $identity['description'],
            'subjectChecked' => $bundle->isMessageSignature() || $digest !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function verifyWithPublicKey(Bundle $bundle, ?string $digest, ?string $publicKeyPem): array
    {
        if ($publicKeyPem === null || trim($publicKeyPem) === '') {
            return ['attempted' => false, 'reason' => 'this bundle references a managed key — supply publicKeyPem to verify'];
        }

        if ($bundle->isMessageSignature() && $digest === null) {
            return [
                'attempted' => false,
                'reason' => 'messageSignature bundles verify against an artifact — supply artifactSha256',
            ];
        }

        try {
            if ($bundle->isMessageSignature()) {
                new SigstoreVerifier()->verifyArtifactDigestWithPublicKey(
                    bundle: $bundle,
                    algorithm: 'sha256',
                    hexDigest: (string) $digest,
                    publicKeyPem: $publicKeyPem,
                    trustedRoot: TrustedRootProvider::get(),
                    expectedHint: $bundle->publicKeyHint,
                );
            } else {
                new SigstoreVerifier()->verifyWithPublicKey(
                    bundle: $bundle,
                    publicKeyPem: $publicKeyPem,
                    trustedRoot: TrustedRootProvider::get(),
                    expectedHint: $bundle->publicKeyHint,
                    subjectPolicy: $digest !== null ? new SubjectPolicy(algorithm: 'sha256', hexDigest: $digest) : null,
                );
            }
        } catch (SigstoreException $e) {
            return ['attempted' => true, 'verified' => false, 'reason' => $e->getMessage()];
        }

        return ['attempted' => true, 'verified' => true, 'identity' => ['mode' => 'publicKey']];
    }

    /**
     * @param array<string, mixed>|null $certificate
     * @param array<string, mixed>      $input
     *
     * @return array{policy: ?IdentityPolicy, description: array<string, mixed>, reason: string}
     */
    private function identityPolicy(?array $certificate, array $input): array
    {
        $expectedSan = is_string($input['expectedSan'] ?? null) && $input['expectedSan'] !== '' ? $input['expectedSan'] : null;
        $expectedSanRegex = is_string($input['expectedSanRegex'] ?? null) && $input['expectedSanRegex'] !== '' ? $input['expectedSanRegex'] : null;
        $expectedIssuer = is_string($input['expectedIssuer'] ?? null) && $input['expectedIssuer'] !== '' ? $input['expectedIssuer'] : null;

        $mode = $expectedSan !== null || $expectedSanRegex !== null || $expectedIssuer !== null ? 'expected' : 'observed';

        if ($expectedSanRegex !== null) {
            $issuer = $expectedIssuer ?? self::GITHUB_ACTIONS_ISSUER;

            return [
                'policy' => IdentityPolicy::sanRegex(pattern: $expectedSanRegex, issuer: $issuer),
                'description' => ['mode' => $mode, 'sanRegex' => $expectedSanRegex, 'issuer' => $issuer],
                'reason' => '',
            ];
        }

        $observedSan = $certificate !== null ? CertificateDetails::firstSanValue($certificate) : null;
        $observedIssuer = $certificate['oidcIssuer'] ?? null;

        $san = $expectedSan ?? $observedSan;
        $issuer = $expectedIssuer ?? $observedIssuer;

        if ($san === null || $issuer === null) {
            return [
                'policy' => null,
                'description' => [],
                'reason' => 'the certificate exposes no SAN or OIDC issuer — supply expectedSan and expectedIssuer',
            ];
        }

        return [
            'policy' => new IdentityPolicy(san: $san, issuer: $issuer, sanIsRegex: false),
            'description' => ['mode' => $mode, 'san' => $san, 'issuer' => $issuer],
            'reason' => '',
        ];
    }
}
