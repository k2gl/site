<?php

declare(strict_types=1);

namespace App\Endpoint;

use App\Http\HttpProblem;
use K2gl\SdJwt\Exception\SdJwtException;
use K2gl\SdJwt\Jws\JwsSigner;
use K2gl\SdJwt\Sd;
use K2gl\SdJwt\SdJwtIssuer;

/**
 * Issues a demo SD-JWT with a fresh, throwaway ES256 key — for learning and for
 * feeding the debugger. Nothing is stored; the private key exists only in this
 * response.
 */
final class SdJwtIssue
{
    private const int MAX_CLAIMS = 50;

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $claims = $input['claims'] ?? null;
        $disclose = $input['disclose'] ?? [];

        if (! is_array($claims) || $claims === [] || array_is_list($claims)) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"claims" is required (a non-empty JSON object).');
        }

        if (count($claims) > self::MAX_CLAIMS) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: 'At most ' . self::MAX_CLAIMS . ' claims.');
        }

        if (! is_array($disclose) || ! array_is_list($disclose)) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: '"disclose" must be a list of claim names to make selectively disclosable.');
        }

        foreach ($disclose as $name) {
            if (! is_string($name) || ! array_key_exists($name, $claims)) {
                throw new HttpProblem(status: 422, code: 'invalid_input', message: 'Unknown claim in "disclose": ' . json_encode($name));
            }

            $claims[$name] = Sd::hide($claims[$name]);
        }

        [$privatePem, $publicPem, $publicJwk] = $this->ephemeralEs256Key();

        try {
            $sdJwt = new SdJwtIssuer(JwsSigner::es256FromPem($privatePem))->issue($claims);
        } catch (SdJwtException $e) {
            throw new HttpProblem(status: 422, code: 'invalid_input', message: $e->getMessage());
        }

        return ['result' => [
            'sdJwt' => $sdJwt->toCompact(),
            'disclosures' => count($sdJwt->disclosures),
            'issuerKey' => [
                'publicPem' => $publicPem,
                'publicJwk' => $publicJwk,
                'privatePem' => $privatePem,
            ],
            'note' => 'Demo credential signed by a throwaway key generated for this request — do not use these keys for anything real.',
        ]];
    }

    /** @return array{0: string, 1: string, 2: array<string, string>} */
    private function ephemeralEs256Key(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($key === false || ! openssl_pkey_export($key, $privatePem)) {
            throw new HttpProblem(status: 500, code: 'internal', message: 'Key generation failed.');
        }

        $details = openssl_pkey_get_details($key);
        $publicPem = $details['key'];

        $b64url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        $publicJwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $b64url(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            'y' => $b64url(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        ];

        return [$privatePem, $publicPem, $publicJwk];
    }
}
