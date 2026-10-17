<?php

namespace SameOldNick\OpenObserve\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SameOldNick\OpenObserve\DTOs\ApiResponse;

class OpenObserveClient
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $organization,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?array $options = null,
    ) {
        //
    }

    /**
     * Send a single log entry to the OpenObserve instance.
     */
    public function sendSingleLog(string $stream, string $logData): ApiResponse
    {
        if (! $stream) {
            throw new InvalidArgumentException('Stream name cannot be empty.');
        }

        $response = $this->createHttpClient()
            ->withBody($logData, 'application/json')
            ->post($this->buildApiEndpointPath("/{$stream}/_json"));

        return $this->transformResponse($response);
    }

    /**
     * Send multiple log entries to the OpenObserve instance.
     */
    public function sendMultiLogs(string $stream, string $multiLogData): ApiResponse
    {
        if (! $stream) {
            throw new InvalidArgumentException('Stream name cannot be empty.');
        }

        $response = $this->createHttpClient()
            ->withBody($multiLogData, 'application/json')
            ->post($this->buildApiEndpointPath("/{$stream}/_multi"));

        return $this->transformResponse($response);
    }

    /**
     * Build the API endpoint URL path for a given suffix.
     */
    public function buildApiEndpointPath(string $suffix): string
    {
        return "/api/{$this->organization}/".ltrim($suffix, '/');
    }

    /**
     * Create a new HTTP client instance with the necessary configuration.
     */
    protected function createHttpClient(): PendingRequest
    {
        $request = Http::throw()->acceptJson();

        if ($this->username || $this->password) {
            $request = $request->withBasicAuth($this->username ?? '', $this->password ?? '');
        }

        if ($this->options) {
            $request = $request->withOptions($this->options);
        }

        return $request->baseUrl($this->baseUrl);
    }

    /**
     * Transform the HTTP response into an ApiResponse object.
     */
    protected function transformResponse(Response $response): ApiResponse
    {
        return ApiResponse::fromResponse($response);
    }
}
