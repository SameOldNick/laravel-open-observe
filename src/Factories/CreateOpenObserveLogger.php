<?php

namespace SameOldNick\OpenObserve\Factories;

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use SameOldNick\OpenObserve\Monolog\OpenObserveHandler;
use SameOldNick\OpenObserve\Support\OpenObserveClient;

class CreateOpenObserveLogger
{
    public function __construct()
    {
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
        return new OpenObserveClient(
            baseUrl: $config['base_url'] ?? '',
            organization: $config['organization'] ?? '',
            username: $config['auth']['username'] ?? '',
            password: $config['auth']['password'] ?? '',
            options: $config['http_options'] ?? null,
        );
    }

    /**
     * Create a new OpenObserve handler instance.
     */
    protected function createHandler(OpenObserveClient $client, array $config): HandlerInterface
    {
        $handler = new OpenObserveHandler(
            client: $client,
            verifyResponse: (bool) $config['verify_response'] ?? true,
            stream: $config['stream'] ?? '',
            level: $config['level'] ?? Level::Debug,
            bubble: $config['bubble'] ?? true,
        );

        return $handler;
    }
}
