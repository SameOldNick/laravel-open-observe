<?php

namespace SameOldNick\OpenObserve\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SameOldNick\OpenObserve\DTOs\ApiResponse;
use SameOldNick\OpenObserve\Support\OpenObserveConnectionTester;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;

class OpenObserveConnectionTesterTest extends TestCase
{
    use ApiResponsePayload;

    public function test_returns_success_when_open_observe_accepts_the_test_entry(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $result = (new OpenObserveConnectionTester)->test($this->channelConfig());

        $this->assertTrue($result->successful);
        $this->assertSame('test-stream', $result->stream);
        $this->assertInstanceOf(ApiResponse::class, $result->response);
        $this->assertNull($result->exception);
        $this->assertSame('Successfully connected to OpenObserve and sent a test log entry.', $result->message);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/test-org/test-stream/_json'
            && json_decode($request->body(), true)['message'] === 'Test log entry from Laravel application.');
    }

    public function test_uses_the_configured_token_for_authentication(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $config = $this->channelConfig();
        $config['auth'] = ['token' => 'Basic '.base64_encode('user:secret')];

        $result = (new OpenObserveConnectionTester)->test($config);

        $this->assertTrue($result->successful);

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Basic '.base64_encode('user:secret')]);
    }

    public function test_uses_the_provided_stream_and_invokes_request_and_response_callbacks(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $capturedRequest = null;
        $capturedResponse = null;

        $result = (new OpenObserveConnectionTester)->test(
            config: $this->channelConfig(),
            stream: 'custom-stream',
            onRequest: function (RequestInterface $request) use (&$capturedRequest): void {
                $capturedRequest = $request;
            },
            onResponse: function (ResponseInterface $response) use (&$capturedResponse): void {
                $capturedResponse = $response;
            },
        );

        $this->assertTrue($result->successful);
        $this->assertSame('custom-stream', $result->stream);
        $this->assertInstanceOf(RequestInterface::class, $capturedRequest);
        $this->assertInstanceOf(ResponseInterface::class, $capturedResponse);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/test-org/custom-stream/_json');
    }

    public function test_returns_failure_when_no_stream_is_configured(): void
    {
        Http::fake();

        $result = (new OpenObserveConnectionTester)->test($this->channelConfig(stream: ''));

        $this->assertFalse($result->successful);
        $this->assertSame('No stream specified.', $result->message);
        $this->assertNull($result->stream);

        Http::assertNothingSent();
    }

    public function test_returns_failure_when_open_observe_reports_failed_entries(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [[
                'name' => 'test-stream',
                'successful' => 0,
                'failed' => 1,
            ]])),
        ]);

        $result = (new OpenObserveConnectionTester)->test($this->channelConfig());

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('Failed to send test log entry.', $result->message);
        $this->assertSame('test-stream', $result->stream);
        $this->assertInstanceOf(ApiResponse::class, $result->response);
        $this->assertNull($result->exception);
    }

    public function test_returns_failure_when_the_connection_fails(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $result = (new OpenObserveConnectionTester)->test($this->channelConfig());

        $this->assertFalse($result->successful);
        $this->assertSame('Error connecting to OpenObserve: Connection timed out', $result->message);
        $this->assertSame('test-stream', $result->stream);
        $this->assertNull($result->response);
        $this->assertInstanceOf(ConnectionException::class, $result->exception);
    }

    private function channelConfig(string $stream = 'test-stream'): array
    {
        return [
            'base_url' => 'https://openobserve.test',
            'organization' => 'test-org',
            'stream' => $stream,
            'auth' => [
                'username' => 'user',
                'password' => 'secret',
            ],
            'http_options' => [
                'timeout' => 5,
                'connect_timeout' => 5,
                'verify_response' => true,
            ],
        ];
    }
}
