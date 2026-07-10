<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\ProvenanceInspect;
use App\Http\HttpProblem;
use App\Tests\Support\ApiTestCase;
use K2gl\Sigstore\Bundle;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(ProvenanceInspect::class)]
final class ProvenanceInspectTest extends ApiTestCase
{
    public function testRendersASlsaV1Statement(): void
    {
        // arrange: a minimal but complete v1 provenance statement
        $statement = [
            '_type' => 'https://in-toto.io/Statement/v1',
            'subject' => [['name' => 'pkg.zip', 'digest' => ['sha256' => str_repeat('ab', 32)]]],
            'predicateType' => 'https://slsa.dev/provenance/v1',
            'predicate' => [
                'buildDefinition' => [
                    'buildType' => 'https://actions.github.io/buildtypes/workflow/v1',
                    'externalParameters' => ['workflow' => ['ref' => 'refs/tags/1.0.0']],
                    'resolvedDependencies' => [['uri' => 'git+https://github.com/acme/widget', 'digest' => ['gitCommit' => str_repeat('c', 40)]]],
                ],
                'runDetails' => [
                    'builder' => ['id' => 'https://github.com/actions/runner'],
                    'metadata' => ['invocationId' => 'run-1'],
                ],
            ],
        ];

        // act
        $result = new ProvenanceInspect()->handle(['statement' => $statement])['result'];

        // assert
        fact($result['predicateType'])->is('https://slsa.dev/provenance/v1');
        fact($result['subjects'][0]['name'])->is('pkg.zip');
        fact($result['slsa']['version'])->is('v1');
        fact($result['slsa']['builderId'])->is('https://github.com/actions/runner');
        fact($result['slsa']['invocationId'])->is('run-1');
    }

    public function testRendersAV02EnvelopeFromARealBundle(): void
    {
        // arrange: the DSSE envelope out of the sigstore-js provenance bundle (v0.2)
        $bundle = json_decode(self::fixture('bundle-provenance.json'), associative: true);
        $envelope = [
            'payload' => $bundle['dsseEnvelope']['payload'],
            'payloadType' => $bundle['dsseEnvelope']['payloadType'],
            'signatures' => [['sig' => $bundle['dsseEnvelope']['signatures'][0]['sig']]],
        ];

        // act: no key — decode only
        $result = new ProvenanceInspect()->handle(['envelope' => $envelope])['result'];

        // assert
        fact($result['predicateType'])->is('https://slsa.dev/provenance/v0.2');
        fact($result['slsa']['version'])->is('v0.2');
        fact($result['slsa']['builderId'])->notNull();
        fact($result['verification']['attempted'])->false();
    }

    public function testNonSlsaPredicateFallsBackToRawJson(): void
    {
        // arrange
        $statement = [
            '_type' => 'https://in-toto.io/Statement/v1',
            'subject' => [['name' => 'x', 'digest' => ['sha256' => str_repeat('11', 32)]]],
            'predicateType' => 'https://spdx.dev/Document',
            'predicate' => ['spdxVersion' => 'SPDX-2.3'],
        ];

        // act
        $result = new ProvenanceInspect()->handle(['statement' => $statement])['result'];

        // assert
        fact($result)->arrayNotHasKey('slsa');
        fact($result['predicate']['spdxVersion'])->is('SPDX-2.3');
    }

    public function testGarbageIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new ProvenanceInspect()->handle(['statement' => 'not json']))
            ->throws(HttpProblem::class);
    }

    public function testMissingInputIsRejected(): void
    {
        fact(static fn (): array => new ProvenanceInspect()->handle([]))
            ->throws(HttpProblem::class, 'Provide "statement"');
    }
}
