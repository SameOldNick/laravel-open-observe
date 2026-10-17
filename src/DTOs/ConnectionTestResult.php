<?php

namespace SameOldNick\OpenObserve\DTOs;

use Throwable;

class ConnectionTestResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly string $message,
        public readonly ?string $stream = null,
        public readonly ?ApiResponse $response = null,
        public readonly ?Throwable $exception = null,
    ) {
        //
    }

    /**
     * Create a successful connection test result.
     */
    public static function success(string $stream, ApiResponse $response): self
    {
        return new self(
            successful: true,
            message: 'Successfully connected to OpenObserve and sent a test log entry.',
            stream: $stream,
            response: $response,
        );
    }

    /**
     * Create a failed connection test result.
     */
    public static function failure(
        string $message,
        ?string $stream = null,
        ?ApiResponse $response = null,
        ?Throwable $exception = null,
    ): self {
        return new self(
            successful: false,
            message: $message,
            stream: $stream,
            response: $response,
            exception: $exception,
        );
    }
}
