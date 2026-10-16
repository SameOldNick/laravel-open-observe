<?php

namespace SameOldNick\OpenObserve\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class ApiResponseStreamStatus implements Arrayable
{
    /**
     * Create a new class instance.
     *
     * @param  string  $stream  The name of the stream.
     * @param  int  $successCount  The number of successful log entries.
     * @param  int  $failureCount  The number of failed log entries.
     */
    public function __construct(
        public readonly string $stream,
        public readonly int $successCount,
        public readonly int $failureCount,
    ) {
        //
    }

    /**
     * Convert the object to an array representation.
     *
     * @return array The array representation of the object.
     */
    public function toArray(): array
    {
        return [
            'stream' => $this->stream,
            'successful' => $this->successCount,
            'failed' => $this->failureCount,
        ];
    }
}
