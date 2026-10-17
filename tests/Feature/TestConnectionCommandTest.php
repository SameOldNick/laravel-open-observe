<?php

namespace SameOldNick\OpenObserve\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;

class TestConnectionCommandTest extends TestCase
{
    use ApiResponsePayload;

    public function test_command_succeeds_when_the_connection_works(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->artisan('openobserve:test-connection')
            ->expectsOutput('Sending test log entry to OpenObserve stream: test-stream')
            ->expectsOutput('Successfully connected to OpenObserve and sent a test log entry.')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_no_stream_is_configured(): void
    {
        config()->set('logging.channels.openobserve.stream', '');

        $this->artisan('openobserve:test-connection')
            ->expectsOutput('No stream specified.')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_open_observe_rejects_the_test_entry(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [[
                'name' => 'test-stream',
                'successful' => 0,
                'failed' => 1,
            ]])),
        ]);

        $this->artisan('openobserve:test-connection')
            ->expectsOutputToContain('Failed to send test log entry.')
            ->assertExitCode(1);
    }

    public function test_command_supports_stream_and_debug_options(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->artisan('openobserve:test-connection', [
            '--stream' => 'custom-stream',
            '--debug' => true,
        ])
            ->expectsOutput('Debug mode enabled. Logging HTTP requests and responses.')
            ->expectsOutput('Sending test log entry to OpenObserve stream: custom-stream')
            ->expectsOutputToContain('Request: POST')
            ->expectsOutputToContain('Response: 200')
            ->assertExitCode(0);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/test-org/custom-stream/_json');
    }
}
