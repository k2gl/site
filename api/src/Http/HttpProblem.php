<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * A client-facing failure with a stable machine code; the router turns it into
 * the uniform {"ok":false,"error":{...}} response.
 */
final class HttpProblem extends RuntimeException
{
    // Exception already owns an untyped $code, so the machine code lives here.
    public readonly string $errorCode;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $code,
        string $message,
        public readonly array $headers = [],
    ) {
        $this->errorCode = $code;
        parent::__construct($message);
    }
}
