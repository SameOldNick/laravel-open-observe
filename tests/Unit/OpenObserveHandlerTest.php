<?php

namespace SameOldNick\OpenObserve\Tests\Unit;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use SameOldNick\OpenObserve\DTOs\ApiResponse;
use SameOldNick\OpenObserve\DTOs\ApiResponseStreamStatus;
use SameOldNick\OpenObserve\Exceptions\InvalidResponse;
use SameOldNick\OpenObserve\Monolog\OpenObserveHandler;
use SameOldNick\OpenObserve\Support\OpenObserveClient;
use SameOldNick\OpenObserve\Tests\TestCase;

class OpenObserveHandlerTest extends TestCase
{
    public function test_writes_a_single_log_entry(): void
    {
        $capturedLog = null;

        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')
            ->once()
            ->withArgs(function (string $stream, string $log) use (&$capturedLog): bool {
                $capturedLog = $log;

                return $stream === 'test-stream';
            })
            ->andReturn($this->successfulResponse());

        $handler = $this->makeHandler($client);

        $this->assertFalse($handler->handle($this->makeRecord()));

        $this->assertIsString($capturedLog);
        $this->assertStringEndsWith("\n", $capturedLog);

        $payload = json_decode($capturedLog, true);

        $this->assertSame('Test log message', $payload['message']);
        $this->assertSame(200, $payload['level']);
        $this->assertSame('openobserve', $payload['channel']);
    }

    public function test_batch_logging_sends_newline_delimited_entries(): void
    {
        $capturedBatch = null;

        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')
            ->once()
            ->withArgs(function (string $stream, string $batch) use (&$capturedBatch): bool {
                $capturedBatch = $batch;

                return $stream === 'test-stream';
            })
            ->andReturn($this->successfulResponse());

        $this->makeHandler($client)->handleBatch([
            $this->makeRecord(Level::Info, 'First log message'),
            $this->makeRecord(Level::Warning, 'Second log message'),
        ]);

        $entries = array_map(
            fn (string $entry): array => json_decode($entry, true),
            explode("\n", $capturedBatch),
        );

        $this->assertCount(2, $entries);
        $this->assertSame('First log message', $entries[0]['message']);
        $this->assertSame(200, $entries[0]['level']);
        $this->assertSame('openobserve', $entries[0]['channel']);
        $this->assertSame('Second log message', $entries[1]['message']);
        $this->assertSame(300, $entries[1]['level']);
    }

    public function test_does_not_handle_records_below_the_configured_level(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldNotReceive('sendSingleLog');

        $handler = $this->makeHandler($client, level: Level::Warning);

        $this->assertFalse($handler->isHandling($this->makeRecord(Level::Info)));
        $this->assertTrue($handler->isHandling($this->makeRecord(Level::Warning)));
        $this->assertFalse($handler->handle($this->makeRecord(Level::Info)));
    }

    public function test_throws_when_a_single_log_response_reports_failed_entries(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')
            ->once()
            ->andReturn($this->failedResponse());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_throws_when_a_single_log_response_has_an_error_code(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')
            ->once()
            ->andReturn($this->failedResponse(failed: 0, code: 400));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_throws_when_a_batch_response_reports_failed_entries(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')
            ->once()
            ->andReturn($this->failedResponse());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve');

        $this->makeHandler($client)->handleBatch([$this->makeRecord()]);
    }

    public function test_includes_the_original_response_body_when_verification_fails(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')
            ->once()
            ->andReturn(new ApiResponse(500, [], $this->makeHttpResponse('Failure body', 500)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve: Failure body');

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_does_not_verify_single_log_responses_when_disabled(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')
            ->once()
            ->andReturn($this->failedResponse());

        $this->assertFalse($this->makeHandler($client, verifyResponse: false)->handle($this->makeRecord()));
    }

    public function test_does_not_verify_batch_responses_when_disabled(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')
            ->once()
            ->andReturn($this->failedResponse());

        $this->makeHandler($client, verifyResponse: false)->handleBatch([$this->makeRecord()]);

        $this->addToAssertionCount(1);
    }

    public function test_wraps_request_exceptions_from_single_logs(): void
    {
        $exception = new RequestException($this->makeHttpResponse('Unauthorized', 401));

        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')->once()->andThrow($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve: '.$exception->getMessage());

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_wraps_request_exceptions_from_batches(): void
    {
        $exception = new RequestException($this->makeHttpResponse('Unauthorized', 401));

        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')->once()->andThrow($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve: '.$exception->getMessage());

        $this->makeHandler($client)->handleBatch([$this->makeRecord()]);
    }

    public function test_wraps_connection_exceptions_from_single_logs(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')->once()->andThrow(new ConnectionException('Connection timed out'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to OpenObserve: Connection timed out');

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_wraps_connection_exceptions_from_batches(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')->once()->andThrow(new ConnectionException('Connection timed out'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to OpenObserve: Connection timed out');

        $this->makeHandler($client)->handleBatch([$this->makeRecord()]);
    }

    public function test_throws_when_a_single_log_response_is_invalid(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendSingleLog')->once()->andThrow(new InvalidResponse('Invalid response body'));

        $this->expectException(RuntimeException::class);

        $this->makeHandler($client)->handle($this->makeRecord());
    }

    public function test_wraps_invalid_responses_from_batches(): void
    {
        $client = $this->mock(OpenObserveClient::class);
        $client->shouldReceive('sendMultiLogs')->once()->andThrow(new InvalidResponse('Invalid response body'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to send log to OpenObserve: Invalid response body');

        $this->makeHandler($client)->handleBatch([$this->makeRecord()]);
    }

    private function makeHandler(
        OpenObserveClient $client,
        bool $verifyResponse = true,
        int|string|Level $level = Level::Debug,
    ): OpenObserveHandler {
        return new OpenObserveHandler(
            client: $client,
            stream: 'test-stream',
            verifyResponse: $verifyResponse,
            level: $level,
        );
    }

    private function makeRecord(Level $level = Level::Info, string $message = 'Test log message'): LogRecord
    {
        return new LogRecord(new DateTimeImmutable, 'openobserve', $level, $message);
    }

    private function successfulResponse(): ApiResponse
    {
        return new ApiResponse(200, [
            new ApiResponseStreamStatus('test-stream', 1, 0),
        ]);
    }

    private function failedResponse(int $failed = 1, int $code = 200): ApiResponse
    {
        return new ApiResponse($code, [
            new ApiResponseStreamStatus('test-stream', 0, $failed),
        ]);
    }

    private function makeHttpResponse(string $body, int $status): Response
    {
        return new Response(new Psr7Response($status, [], $body));
    }
}
