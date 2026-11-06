<?php

namespace SameOldNick\OpenObserve\Factories;

use Illuminate\Support\Arr;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use SameOldNick\OpenObserve\Monolog\OpenObserveHandler;
use SameOldNick\OpenObserve\Support\OpenObserveClient;

class CreateOpenObserveLogger
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        protected readonly CreateOpenObserveClient $clientFactory,
    ) {
        //
    }

    /**
     * Build a new OpenObserve logger instance.
     */
    public function build(array $config): Logger
    {
        $client = $this->createClient($config);
        $handler = $this->createHandler($client, $config);

        // Create logger instance
        $logger = new Logger($config['name'] ?? 'openobserve');
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Invoke the class instance.
     */
    public function __invoke(array $config): Logger
    {
        return $this->build($config);
    }

    /**
     * Create a new OpenObserve client instance.
     */
    protected function createClient(array $config): OpenObserveClient
    {
        return $this->clientFactory->build($config);
    }

    /**
     * Create a new OpenObserve handler instance.
     */
    protected function createHandler(OpenObserveClient $client, array $config): HandlerInterface
    {
        $handler = new OpenObserveHandler(
            client: $client,
            verifyResponse: (bool) Arr::boolean($config, 'http_options.verify_response', true),
            stream: Arr::get($config, 'stream', ''),
            level: Arr::get($config, 'level', Level::Debug),
            bubble: Arr::boolean($config, 'bubble', true),
        );

        return $handler;
    }
}
