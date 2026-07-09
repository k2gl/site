<?php

declare(strict_types=1);

use App\Endpoint\ComposerAttestations;
use App\Endpoint\DsseInspect;
use App\Endpoint\Health;
use App\Endpoint\SdJwtInspect;
use App\Endpoint\SigstoreInspect;
use App\Http\Input;
use App\Http\JsonResponse;
use App\RateLimiter;

return [
    'GET /api/health' => static fn (): JsonResponse => JsonResponse::ok(new Health()->handle()),

    'POST /api/v1/dsse/inspect' => static function (): JsonResponse {
        RateLimiter::guard(bucket: 'inspect', capacity: 20, refillPerMinute: 10.0);

        return JsonResponse::ok(new DsseInspect()->handle(Input::json()));
    },

    'POST /api/v1/sigstore/inspect' => static function (): JsonResponse {
        RateLimiter::guard(bucket: 'inspect', capacity: 20, refillPerMinute: 10.0);

        return JsonResponse::ok(new SigstoreInspect()->handle(Input::json()));
    },

    'POST /api/v1/sd-jwt/inspect' => static function (): JsonResponse {
        RateLimiter::guard(bucket: 'inspect', capacity: 20, refillPerMinute: 10.0);

        return JsonResponse::ok(new SdJwtInspect()->handle(Input::json()));
    },

    // Downloads the dist zip and talks to GitHub — the strictest bucket.
    'GET /api/v1/composer/attestations' => static function (): JsonResponse {
        RateLimiter::guard(bucket: 'composer', capacity: 6, refillPerMinute: 3.0);

        return JsonResponse::ok(new ComposerAttestations()->handle($_GET));
    },
];
