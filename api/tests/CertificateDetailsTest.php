<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\HttpProblem;
use App\Sigstore\CertificateDetails;
use App\Tests\Support\ApiTestCase;
use K2gl\Sigstore\Bundle;
use PHPUnit\Framework\Attributes\CoversClass;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(CertificateDetails::class)]
final class CertificateDetailsTest extends ApiTestCase
{
    public function testDecodesAFulcioLeafCertificate(): void
    {
        // arrange
        $leaf = (string) Bundle::fromJson(self::fixture('bundle-provenance.json'))->leafCertificate;

        // act
        $details = CertificateDetails::fromDer($leaf);

        // assert: identity surface
        fact($details['oidcIssuer'])->is('https://token.actions.githubusercontent.com');
        fact(CertificateDetails::firstSanValue($details))
            ->is('https://github.com/sigstore/sigstore-js/.github/workflows/release.yml@refs/heads/main');

        // assert: v1 raw and v2 DER-wrapped extensions both decode to clean strings
        fact($details['fulcio']['githubWorkflowRepository'])->is('sigstore/sigstore-js');
        fact($details['fulcio']['sourceRepositoryUri'])->is('https://github.com/sigstore/sigstore-js');
        fact($details['fulcio']['runnerEnvironment'])->is('github-hosted');

        // assert: validity window is exposed as ISO timestamps
        fact($details['notBefore'])->matchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/');
        fact($details['notAfter'])->matchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/');
    }

    public function testNonCertificateBytesAreRejectedAsClientInput(): void
    {
        fact(static fn (): array => CertificateDetails::fromDer('definitely not DER'))
            ->throws(HttpProblem::class, 'could not be parsed');
    }
}
