<?php

namespace SameOldNick\OpenObserve\Commands;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use SameOldNick\OpenObserve\Support\ObservesOpenObserveClient;

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
    public function handle()
    {
        $config = config($this->option('config-key') ?? 'logging.channels.openobserve', []);
        $stream = $this->option('stream') ?? $config['stream'] ?? null;

        if (! $stream) {
            $this->error('No stream specified.');

            return;
        }

        $client = $this->createClient($config);

        try {
            $this->info('Sending test log entry to OpenObserve stream: '.$stream);

            $response = $client->sendSingleLog($stream, json_encode(['message' => 'Test log entry from Laravel application.']));

            if ($response->isSuccessful() && $response->getFailedCount() === 0) {
                $this->info('Successfully connected to OpenObserve and sent a test log entry.');
            } else {
                $this->error('Failed to send test log entry. Response: '.$response->originalResponse?->body() ?? json_encode($response->toArray()));
            }
        } catch (Exception $e) {
            $this->error('Error connecting to OpenObserve: '.$e->getMessage());
        }
    }

    protected function createClient(array $config): ObservesOpenObserveClient
    {
        $client = new ObservesOpenObserveClient(
            baseUrl: $config['base_url'] ?? '',
            organization: $config['organization'] ?? '',
            username: $config['auth']['username'] ?? '',
            password: $config['auth']['password'] ?? '',
            options: $config['http_options'] ?? null,
        );

        if ($this->option('debug')) {
            $this->info('Debug mode enabled. Logging HTTP requests and responses.');

            $client->setOnRequestCallback(function ($request) {
                $this->info('Request: '.$request->getMethod().' '.$request->getUri());
                $this->info('Request Headers: '.json_encode($request->getHeaders(), JSON_PRETTY_PRINT));
                $this->info('Request Body: '.$request->getBody());
            });

            $client->setOnResponseCallback(function ($response) {
                $this->info('Response: '.$response->getStatusCode().' '.$response->getBody());
            });
        }

        return $client;
    }
}
