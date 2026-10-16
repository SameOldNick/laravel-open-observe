<?php

namespace SameOldNick\OpenObserve\Support;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ObservesOpenObserveClient extends OpenObserveClient
{
    /**
     * The recorded requests.
     *
     * @var RequestInterface[]
     */
    protected array $requests = [];

    /**
     * The recorded responses.
     *
     * @var ResponseInterface[]
     */
    protected array $responses = [];

    /**
     * The callback to be executed when a request is made.
     */
    protected ?Closure $onRequestCallback = null;

    /**
     * The callback to be executed when a response is received.
     */
    protected ?Closure $onResponseCallback = null;

    /**
     * Sets callback when request is made
     *
     * @param  callable(RequestInterface|null)|null  $callback
     */
    public function setOnRequestCallback(?callable $callback): self
    {
        $this->onRequestCallback = $callback;

        return $this;
    }

    /**
     * Sets callback when response is received
     *
     * @param  callable(ResponseInterface|null)|null  $callback
     */
    public function setOnResponseCallback(?callable $callback): self
    {
        $this->onResponseCallback = $callback;

        return $this;
    }

    /**
     * Get the recorded requests.
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the recorded responses.
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    protected function createHttpClient(): PendingRequest
    {
        $client = parent::createHttpClient();

        return $client
            ->withRequestMiddleware($this->onRequest(...))
            ->withResponseMiddleware($this->onResponse(...));
    }

    /**
     * Middleware to record requests and execute the onRequest callback.
     */
    protected function onRequest(RequestInterface $request): RequestInterface
    {
        $this->requests[] = $request;

        if ($this->onRequestCallback) {
            ($this->onRequestCallback)($request);
        }

        return $request;
    }

    /**
     * Middleware to record responses and execute the onResponse callback.
     */
    protected function onResponse(ResponseInterface $response): ResponseInterface
    {
        $this->responses[] = $response;

        if ($this->onResponseCallback) {
            ($this->onResponseCallback)($response);
        }

        return $response;
    }
}
