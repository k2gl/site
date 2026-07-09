<?php

declare(strict_types=1);

namespace App\Tests;

use App\Endpoint\SigstoreInspect;
use App\Http\HttpProblem;
use App\Sigstore\CertificateDetails;
use App\Tests\Support\ApiTestCase;
use App\TrustedRootProvider;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(SigstoreInspect::class)]
#[CoversClass(TrustedRootProvider::class)]
#[CoversClass(CertificateDetails::class)]
final class SigstoreInspectTest extends ApiTestCase
{
    private const string ARTIFACT_SHA256 = 'a0cfc71271d6e278e57cd332ff957c3f7043fdda354c4cbb190a30d56efa01bf';

    protected function setUp(): void
    {
        // Pin the trusted root so no test ever refreshes over TUF.
        putenv('TRUSTED_ROOT_PATH=' . self::fixturePath('trusted-root-public-good.json'));
        TrustedRootProvider::reset();
    }

    protected function tearDown(): void
    {
        putenv('TRUSTED_ROOT_PATH');
        TrustedRootProvider::reset();
    }

    public function testVerifiesADsseProvenanceBundleAgainstItsObservedIdentity(): void
    {
        // act
        $response = new SigstoreInspect()->handle(['bundle' => self::fixture('bundle-provenance.json')]);

        // assert: display surface
        $result = $response['result'];
        fact($result['certificate']['oidcIssuer'])->is('https://token.actions.githubusercontent.com');
        fact($result['certificate']['san'][0]['type'])->is('URI');
        fact($result['certificate']['fulcio']['githubWorkflowRepository'])->is('sigstore/sigstore-js');
        fact($result['tlog'][0]['kind'])->is('intoto');
        fact($result['dsse']['statement']['predicateType'])->is('https://slsa.dev/provenance/v0.2');

        // assert: real verification against the pinned root
        fact($result['verification']['verified'])->true();
        fact($result['verification']['identity']['mode'])->is('observed');
        fact($result['verification']['subjectChecked'])->false();
        fact($response['meta']['trustedRoot'])->is('pinned');
    }

    public function testVerifiesAMessageSignatureBundleAgainstItsArtifactDigest(): void
    {
        // act
        $response = new SigstoreInspect()->handle([
            'bundle' => self::fixture('conformance-msgsig-v0.3.json'),
            'artifactSha256' => self::ARTIFACT_SHA256,
        ]);

        // assert
        $result = $response['result'];
        fact($result['messageSignature']['digest'])->is(self::ARTIFACT_SHA256);
        fact($result['verification']['verified'])->true();
        fact($result['verification']['subjectChecked'])->true();
    }

    public function testMessageSignatureWithoutDigestIsNotAttempted(): void
    {
        // act
        $response = new SigstoreInspect()->handle(['bundle' => self::fixture('conformance-msgsig-v0.3.json')]);

        // assert
        fact($response['result']['verification']['attempted'])->false();
        fact($response['result']['verification']['reason'])->containsString('artifactSha256');
    }

    public function testForeignExpectedIdentityFailsVerification(): void
    {
        // act
        $response = new SigstoreInspect()->handle([
            'bundle' => self::fixture('bundle-provenance.json'),
            'expectedSan' => 'https://github.com/evil/repository/.github/workflows/release.yml@refs/heads/main',
            'expectedIssuer' => 'https://token.actions.githubusercontent.com',
        ]);

        // assert
        fact($response['result']['verification']['attempted'])->true();
        fact($response['result']['verification']['verified'])->false();
        fact($response['result']['verification']['identity']['mode'])->is('expected');
    }

    public function testMalformedDigestIsRejected(): void
    {
        fact(static fn (): array => new SigstoreInspect()->handle([
            'bundle' => self::fixture('bundle-provenance.json'),
            'artifactSha256' => 'zz42',
        ]))->throws(HttpProblem::class, '64 hex characters');
    }

    public function testGarbageBundleIsRejectedAsClientInput(): void
    {
        fact(static fn (): array => new SigstoreInspect()->handle(['bundle' => '{"mediaType": "nope"}']))
            ->throws(HttpProblem::class);
    }

    public function testMissingBundleIsRejected(): void
    {
        fact(static fn (): array => new SigstoreInspect()->handle([]))
            ->throws(HttpProblem::class, '"bundle" is required');
    }
}
