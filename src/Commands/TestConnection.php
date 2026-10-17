<?php

namespace SameOldNick\OpenObserve\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SameOldNick\OpenObserve\Support\OpenObserveConnectionTester;

#[Signature('openobserve:test-connection
            {--config-key= : The configuration key to use for the OpenObserve client (default: logging.channels.openobserve)}
            {--stream= : The OpenObserve stream to send the test log entry to (default: from config) }
            {--debug : Enable debug output for the HTTP request and response}')]
#[Description('Test the connection to the OpenObserve instance')]
class TestConnection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OpenObserveConnectionTester $tester): int
    {
        $config = (array) config($this->option('config-key') ?? 'logging.channels.openobserve', []);
        $stream = (string) ($this->option('stream') ?? $config['stream'] ?? '');

        if ($stream === '') {
            $this->error('No stream specified.');

            return self::FAILURE;
        }

        $debug = (bool) $this->option('debug');

        if ($debug) {
            $this->info('Debug mode enabled. Logging HTTP requests and responses.');
        }

        $this->info('Sending test log entry to OpenObserve stream: '.$stream);

        $result = $tester->test(
            config: $config,
            stream: $stream,
            onRequest: $debug ? function (RequestInterface $request): void {
                $this->info('Request: '.$request->getMethod().' '.$request->getUri());
                $this->info('Request Headers: '.json_encode($request->getHeaders(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Request Body: '.$request->getBody());
            } : null,
            onResponse: $debug ? function (ResponseInterface $response): void {
                $this->info('Response: '.$response->getStatusCode().' '.$response->getBody());
            } : null,
        );

        if (! $result->successful) {
            $this->error($result->message);

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
