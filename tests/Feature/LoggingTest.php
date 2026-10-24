<?php

namespace SameOldNick\OpenObserve\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;

class LoggingTest extends TestCase
{
    use ApiResponsePayload;

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_sends_the_formatted_log_entry(string $channel): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel($channel)->info('Test log message', ['context' => 'test']);

        Http::assertSent(function (Request $request) use ($channel): bool {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/test-org/test-stream/_json'
                && $request->header('Content-Type') === ['application/json']
                && $request->header('Accept') === ['application/json']
                && $request->header('Authorization') === ['Basic '.base64_encode('user:secret')]
                && $payload['message'] === 'Test log message'
                && $payload['context'] === ['context' => 'test']
                && $payload['channel'] === $channel
                && $payload['level'] === 200;
        });

        Http::assertSentCount(1);
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_uses_the_configured_token(string $channel): void
    {
        $token = 'Basic '.base64_encode('user:secret');
        config()->set("logging.channels.{$channel}.auth", ['token' => $token]);

        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel($channel)->info('Test log message');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === [$token]);
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_with_supported_driver_configurations(string $channel): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel($channel)->info('Test log message');

        Http::assertSent(function (Request $request) use ($channel): bool {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/test-org/test-stream/_json'
                && $payload['message'] === 'Test log message'
                && $payload['channel'] === $channel;
        });

        Http::assertSentCount(1);
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_throws_when_the_api_reports_failed_entries(string $channel): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [[
                'name' => 'test-stream',
                'successful' => 0,
                'failed' => 1,
            ]])),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        Log::channel($channel)->info('Test log message');
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_wraps_http_errors(string $channel): void
    {
        Http::fake([
            '*' => Http::response('Server error', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        Log::channel($channel)->info('Test log message');
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_wraps_connection_errors(string $channel): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to OpenObserve: Connection timed out');

        Log::channel($channel)->info('Test log message');
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_throws_when_the_api_returns_an_error_code(string $channel): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(code: 400)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        Log::channel($channel)->info('Test log message');
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_throws_when_the_response_is_not_json(string $channel): void
    {
        Http::fake([
            '*' => Http::response('Service Unavailable'),
        ]);

        $this->expectException(RuntimeException::class);

        Log::channel($channel)->info('Test log message');
    }

    #[DataProvider('driverChannels')]
    public function test_logging_to_open_observe_ignores_failed_entries_when_response_verification_is_disabled(string $channel): void
    {
        config()->set("logging.channels.{$channel}.http_options.verify_response", false);

        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [[
                'name' => 'test-stream',
                'successful' => 0,
                'failed' => 1,
            ]])),
        ]);

        Log::channel($channel)->info('Test log message');

        Http::assertSentCount(1);
    }

    #[DataProvider('driverChannels')]
    public function test_logging_below_the_configured_level_is_not_sent(string $channel): void
    {
        config()->set("logging.channels.{$channel}.level", 'warning');

        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel($channel)->info('Filtered log message');

        Http::assertNothingSent();

        Log::channel($channel)->warning('Sent log message');

        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)['message'] === 'Sent log message');
    }

    public static function driverChannels(): array
    {
        return [
            'openobserve driver' => ['openobserve-driver'],
            'custom driver' => ['openobserve'],
        ];
    }
}
