<?php

namespace SameOldNick\OpenObserve\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;

class LoggingTest extends TestCase
{
    use ApiResponsePayload;

    public function test_logging_to_open_observe_sends_the_formatted_log_entry(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel('openobserve')->info('Test log message', ['context' => 'test']);

        Http::assertSent(function (Request $request): bool {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/test-org/test-stream/_json'
                && $request->header('Content-Type') === ['application/json']
                && $request->header('Accept') === ['application/json']
                && $request->header('Authorization') === ['Basic '.base64_encode('user:secret')]
                && $payload['message'] === 'Test log message'
                && $payload['context'] === ['context' => 'test']
                && $payload['channel'] === 'openobserve'
                && $payload['level'] === 200;
        });

        Http::assertSentCount(1);
    }

    public function test_logging_to_open_observe_throws_when_the_api_reports_failed_entries(): void
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

        Log::channel('openobserve')->info('Test log message');
    }

    public function test_logging_to_open_observe_wraps_http_errors(): void
    {
        Http::fake([
            '*' => Http::response('Server error', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        Log::channel('openobserve')->info('Test log message');
    }

    public function test_logging_to_open_observe_wraps_connection_errors(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to OpenObserve: Connection timed out');

        Log::channel('openobserve')->info('Test log message');
    }

    public function test_logging_to_open_observe_throws_when_the_api_returns_an_error_code(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(code: 400)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        Log::channel('openobserve')->info('Test log message');
    }

    public function test_logging_to_open_observe_throws_when_the_response_is_not_json(): void
    {
        Http::fake([
            '*' => Http::response('Service Unavailable'),
        ]);

        $this->expectException(RuntimeException::class);

        Log::channel('openobserve')->info('Test log message');
    }

    public function test_logging_to_open_observe_ignores_failed_entries_when_response_verification_is_disabled(): void
    {
        config()->set('logging.channels.openobserve.http_options.verify_response', false);

        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [[
                'name' => 'test-stream',
                'successful' => 0,
                'failed' => 1,
            ]])),
        ]);

        Log::channel('openobserve')->info('Test log message');

        Http::assertSentCount(1);
    }

    public function test_logging_below_the_configured_level_is_not_sent(): void
    {
        config()->set('logging.channels.openobserve.level', 'warning');

        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        Log::channel('openobserve')->info('Filtered log message');

        Http::assertNothingSent();

        Log::channel('openobserve')->warning('Sent log message');

        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)['message'] === 'Sent log message');
    }
}
