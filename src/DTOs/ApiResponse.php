<?php

namespace SameOldNick\OpenObserve\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SameOldNick\OpenObserve\Exceptions\InvalidResponse;

class ApiResponse implements Arrayable
{
    /**
     * Create a new class instance.
     *
     * @param  int  $code  The HTTP status code of the response.
     * @param  ApiResponseStreamStatus[]  $statuses  An array of ApiResponseStreamStatus objects
     * @param  ?Response  $originalResponse  The original HTTP response, if available.
     */
    public function __construct(
        public readonly int $code,
        public readonly array $statuses,
        public readonly ?Response $originalResponse = null,
    ) {
        //
    }

    /**
     * Get the status for a specific stream.
     *
     * @param  string  $stream  The name of the stream.
     * @return ApiResponseStreamStatus|null The status for the specified stream, or null if not found.
     */
    public function getStatusForStream(string $stream): ?ApiResponseStreamStatus
    {
        return Arr::first($this->statuses, fn (ApiResponseStreamStatus $status) => $status->stream === $stream);
    }

    /**
     * Determine if the response was successful (HTTP status code 2xx).
     *
     * @return bool True if the response was successful, false otherwise.
     */
    public function isSuccessful(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    /**
     * Determine if the response was a client error (HTTP status code 4xx).
     *
     * @return bool True if the response was a client error, false otherwise.
     */
    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    /**
     * Determine if the response was a server error (HTTP status code 5xx).
     *
     * @return bool True if the response was a server error, false otherwise.
     */
    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    /**
     * Get the total number of successful log entries across all streams.
     *
     * @return int The total number of successful log entries.
     */
    public function getSuccessfulCount(): int
    {
        return array_reduce($this->statuses, function ($carry, ApiResponseStreamStatus $status) {
            return $carry + $status->successCount;
        }, 0);
    }

    /**
     * Get the total number of failed log entries across all streams.
     *
     * @return int The total number of failed log entries.
     */
    public function getFailedCount(): int
    {
        return array_reduce($this->statuses, function ($carry, ApiResponseStreamStatus $status) {
            return $carry + $status->failureCount;
        }, 0);
    }

    /**
     * Convert the ApiResponse object to an array representation.
     *
     * @return array The array representation of the ApiResponse object.
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'statuses' => array_map(fn (ApiResponseStreamStatus $status) => $status->toArray(), $this->statuses),
        ];
    }

    /**
     * Validate the structure of the response data.
     *
     * @param  array  $data  The response data to validate.
     *
     * @throws ValidationException If the validation fails.
     */
    public static function validateResponseData(array $data): array
    {
        try {
            return Validator::make($data, [
                'code' => 'required|integer',
                'status' => 'sometimes|array',
                'status.*.name' => 'required|string',
                'status.*.successful' => 'required|integer|min:0',
                'status.*.failed' => 'required|integer|min:0',
            ])->validate();
        } catch (ValidationException $e) {
            throw new InvalidResponse('Invalid response body: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create an ApiResponse object from an HTTP response.
     *
     * @param  Response  $response  The HTTP response to transform.
     * @return self The created ApiResponse object.
     */
    public static function fromResponse(Response $response): self
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw new InvalidResponse('Response body is not a valid JSON array.');
        }

        $validated = self::validateResponseData($data);

        return new self(
            code: $validated['code'],
            statuses: array_map(function ($status) {
                return new ApiResponseStreamStatus(
                    stream: $status['name'],
                    successCount: $status['successful'],
                    failureCount: $status['failed'],
                );
            }, $validated['status'] ?? []),
            originalResponse: $response,
        );
    }
}
