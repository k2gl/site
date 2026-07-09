<?php

declare(strict_types=1);

use App\Endpoint\DsseInspect;
use App\Endpoint\Health;
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
];
