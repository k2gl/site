<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use K2gl\Dsse\Envelope;
use K2gl\Dsse\Exception\CryptoException;
use K2gl\Dsse\Exception\InvalidEnvelopeException;
use K2gl\Dsse\Exception\SignatureVerificationFailed;
use K2gl\Dsse\PublicKey;
use K2gl\InToto\Statement;
use K2gl\Slsa\Provenance;
use Throwable;

/**
 * Renders an in-toto Statement (bare, or inside a DSSE envelope) with the SLSA
 * provenance predicate decoded — v1 typed, v0.2 by field.
 */
final class ProvenanceInspect
{
    private const string GITHUB_V1 = 'https://slsa.dev/provenance/v1';

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        [$statement, $verification] = $this->statement($input);

        $result = [
            'predicateType' => $statement->predicateType,
            'subjects' => array_map(
                static fn (object $subject): array => ['name' => $subject->name, 'digest' => $subject->digest],
                $statement->subject,
            ),
        ];

        if ($verification !== null) {
            $result['verification'] = $verification;
        }

        $result += $this->describePredicate($statement);

        return ['result' => $result];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{0: Statement, 1: ?array<string, mixed>}
     */
    private function statement(array $input): array
    {
        $rawStatement = $input['statement'] ?? null;
        $rawEnvelope = $input['envelope'] ?? null;

        try {
            if ($rawEnvelope !== null) {
                $envelope = is_string($rawEnvelope) ? Envelope::fromJson($rawEnvelope) : Envelope::fromArray((array) $rawEnvelope);

                return [Statement::fromEnvelope($envelope), $this->verify($envelope, $input)];
            }

            if (is_string($rawStatement)) {
                return [Statement::fromJson($rawStatement), null];
            }

            if (is_array($rawStatement)) {
                return [Statement::fromArray($rawStatement), null];
            }
        } catch (InvalidEnvelopeException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        } catch (Throwable $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Not an in-toto statement: ' . $e->getMessage());
        }

        throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Provide "statement" (in-toto JSON) or "envelope" (a DSSE envelope carrying one).');
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|null
     */
    private function verify(Envelope $envelope, array $input): ?array
    {
        $pem = $input['publicKeyPem'] ?? null;

        if (! is_string($pem) || trim($pem) === '') {
            return ['attempted' => false, 'reason' => 'no public key supplied — payload decoded, not authenticated'];
        }

        try {
            $envelope->verify(PublicKey::fromPem($pem));
        } catch (CryptoException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Public key rejected: ' . $e->getMessage());
        } catch (SignatureVerificationFailed $e) {
            return ['attempted' => true, 'verified' => false, 'reason' => $e->getMessage()];
        }

        return ['attempted' => true, 'verified' => true];
    }

    /** @return array<string, mixed> */
    private function describePredicate(Statement $statement): array
    {
        if ($statement->predicateType === self::GITHUB_V1) {
            try {
                $provenance = Provenance::fromStatement($statement);

                return ['slsa' => [
                    'version' => 'v1',
                    'builderId' => $provenance->runDetails->builder->id,
                    'buildType' => $provenance->buildDefinition->buildType,
                    'externalParameters' => $provenance->buildDefinition->externalParameters,
                    'resolvedDependencies' => $provenance->buildDefinition->resolvedDependencies,
                    'invocationId' => $provenance->runDetails->metadata?->invocationId,
                    'startedOn' => $provenance->runDetails->metadata?->startedOn,
                ]];
            } catch (Throwable) {
                // fall through to the raw predicate
            }
        }

        $predicate = $statement->predicate;

        if (str_starts_with($statement->predicateType, 'https://slsa.dev/provenance/v0')) {
            return ['slsa' => [
                'version' => 'v0.2',
                'builderId' => $predicate['builder']['id'] ?? null,
                'buildType' => $predicate['buildType'] ?? null,
                'configSource' => $predicate['invocation']['configSource'] ?? null,
                'materials' => $predicate['materials'] ?? [],
            ]];
        }

        return ['predicate' => $predicate];
    }
}
