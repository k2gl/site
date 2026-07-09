<?php

declare(strict_types=1);

namespace App\Sigstore;

use App\Http\HttpProblem;

/**
 * Human-readable view of a Fulcio leaf certificate: subject, validity, SANs and
 * the decoded Fulcio extensions (OID arc 1.3.6.1.4.1.57264.1.*). Display only —
 * cryptographic checks stay in k2gl/sigstore-verify.
 */
final class CertificateDetails
{
    /** @var array<string, array{0: string, 1: bool}> oid => [output key, value is a DER-wrapped UTF8String] */
    private const array FULCIO_EXTENSIONS = [
        '1.3.6.1.4.1.57264.1.1' => ['issuer', false],
        '1.3.6.1.4.1.57264.1.2' => ['githubWorkflowTrigger', false],
        '1.3.6.1.4.1.57264.1.3' => ['githubWorkflowSha', false],
        '1.3.6.1.4.1.57264.1.4' => ['githubWorkflowName', false],
        '1.3.6.1.4.1.57264.1.5' => ['githubWorkflowRepository', false],
        '1.3.6.1.4.1.57264.1.6' => ['githubWorkflowRef', false],
        '1.3.6.1.4.1.57264.1.8' => ['issuerV2', true],
        '1.3.6.1.4.1.57264.1.9' => ['buildSignerUri', true],
        '1.3.6.1.4.1.57264.1.10' => ['buildSignerDigest', true],
        '1.3.6.1.4.1.57264.1.11' => ['runnerEnvironment', true],
        '1.3.6.1.4.1.57264.1.12' => ['sourceRepositoryUri', true],
        '1.3.6.1.4.1.57264.1.13' => ['sourceRepositoryDigest', true],
        '1.3.6.1.4.1.57264.1.14' => ['sourceRepositoryRef', true],
        '1.3.6.1.4.1.57264.1.15' => ['sourceRepositoryIdentifier', true],
        '1.3.6.1.4.1.57264.1.16' => ['sourceRepositoryOwnerUri', true],
        '1.3.6.1.4.1.57264.1.17' => ['sourceRepositoryOwnerIdentifier', true],
        '1.3.6.1.4.1.57264.1.18' => ['buildConfigUri', true],
        '1.3.6.1.4.1.57264.1.19' => ['buildConfigDigest', true],
        '1.3.6.1.4.1.57264.1.20' => ['buildTrigger', true],
        '1.3.6.1.4.1.57264.1.21' => ['runInvocationUri', true],
        '1.3.6.1.4.1.57264.1.22' => ['sourceRepositoryVisibilityAtSigning', true],
    ];

    /** @return array<string, mixed> */
    public static function fromDer(string $der): array
    {
        $pem = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END CERTIFICATE-----\n";

        $parsed = openssl_x509_parse($pem);

        if ($parsed === false) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'The bundle certificate could not be parsed.');
        }

        /** @var array<string, string> $extensions */
        $extensions = $parsed['extensions'] ?? [];

        $fulcio = [];

        foreach (self::FULCIO_EXTENSIONS as $oid => [$key, $derWrapped]) {
            if (! isset($extensions[$oid])) {
                continue;
            }

            $value = (string) $extensions[$oid];
            $fulcio[$key] = $derWrapped ? self::derUtf8String($value) : $value;
        }

        return [
            'subject' => $parsed['subject'] ?? [],
            'issuer' => $parsed['issuer'] ?? [],
            'serialNumber' => $parsed['serialNumberHex'] ?? null,
            'notBefore' => isset($parsed['validFrom_time_t']) ? gmdate('c', (int) $parsed['validFrom_time_t']) : null,
            'notAfter' => isset($parsed['validTo_time_t']) ? gmdate('c', (int) $parsed['validTo_time_t']) : null,
            'san' => self::subjectAlternativeNames($extensions['subjectAltName'] ?? ''),
            'oidcIssuer' => $fulcio['issuerV2'] ?? $fulcio['issuer'] ?? null,
            'fulcio' => $fulcio,
        ];
    }

    /** @param array<string, mixed> $details */
    public static function firstSanValue(array $details): ?string
    {
        foreach ($details['san'] as $san) {
            if ($san['type'] === 'URI' || $san['type'] === 'email') {
                return $san['value'];
            }
        }

        return $details['san'][0]['value'] ?? null;
    }

    /** @return list<array{type: string, value: string}> */
    private static function subjectAlternativeNames(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $names = [];

        foreach (explode(',', $raw) as $entry) {
            [$type, $value] = array_pad(explode(':', trim($entry), 2), 2, '');
            $names[] = ['type' => $type, 'value' => $value];
        }

        return $names;
    }

    /**
     * Fulcio v2 extensions (.8 and up) are DER-encoded UTF8Strings; v1 values are
     * raw bytes. Strip the 0x0c tag + length when present, pass through otherwise.
     */
    private static function derUtf8String(string $bytes): string
    {
        if ($bytes === '' || ord($bytes[0]) !== 0x0c || strlen($bytes) < 2) {
            return $bytes;
        }

        $length = ord($bytes[1]);
        $offset = 2;

        if (($length & 0x80) !== 0) {
            $lengthBytes = $length & 0x7f;

            if ($lengthBytes === 0 || strlen($bytes) < 2 + $lengthBytes) {
                return $bytes;
            }

            $length = 0;

            for ($i = 0; $i < $lengthBytes; $i++) {
                $length = ($length << 8) | ord($bytes[2 + $i]);
            }

            $offset = 2 + $lengthBytes;
        }

        return substr($bytes, $offset, $length);
    }
}
