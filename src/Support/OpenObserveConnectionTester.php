<?php

namespace SameOldNick\OpenObserve\Support;

use Exception;
use Illuminate\Support\Arr;
use SameOldNick\OpenObserve\DTOs\ConnectionTestResult;

class OpenObserveConnectionTester
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function test(
        array $config,
        ?string $stream = null,
        ?callable $onRequest = null,
        ?callable $onResponse = null,
    ): ConnectionTestResult {
        $stream ??= isset($config['stream']) ? (string) $config['stream'] : null;

        if ($stream === null || $stream === '') {
            return ConnectionTestResult::failure('No stream specified.');
        }

        $client = $this->createClient($config, $onRequest, $onResponse);

        try {
            $response = $client->sendSingleLog(
                $stream,
                json_encode(['message' => 'Test log entry from Laravel application.'], JSON_THROW_ON_ERROR),
            );
        } catch (Exception $e) {
            return ConnectionTestResult::failure(
                message: 'Error connecting to OpenObserve: '.$e->getMessage(),
                stream: $stream,
                exception: $e,
            );
        }

        if ($response->isSuccessful() && $response->getFailedCount() === 0) {
            return ConnectionTestResult::success($stream, $response);
        }

        return ConnectionTestResult::failure(
            message: 'Failed to send test log entry. Response: '.($response->originalResponse?->body() ?? json_encode($response->toArray(), JSON_THROW_ON_ERROR)),
            stream: $stream,
            response: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createClient(
        array $config,
        ?callable $onRequest = null,
        ?callable $onResponse = null,
    ): ObservesOpenObserveClient {
        $username = Arr::get($config, 'auth.username');
        $password = Arr::get($config, 'auth.password');
        $options = Arr::get($config, 'http_options');

        $client = new ObservesOpenObserveClient(
            baseUrl: (string) ($config['base_url'] ?? ''),
            organization: (string) ($config['organization'] ?? ''),
            username: is_string($username) && $username !== '' ? $username : null,
            password: is_string($password) && $password !== '' ? $password : null,
            options: is_array($options) ? $options : null,
        );

        if ($onRequest) {
            $client->setOnRequestCallback($onRequest);
        }

        if ($onResponse) {
            $client->setOnResponseCallback($onResponse);
        }

        return $client;
    }
}
