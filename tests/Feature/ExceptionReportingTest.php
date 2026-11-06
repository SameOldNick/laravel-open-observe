<?php

namespace SameOldNick\OpenObserve\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use SameOldNick\OpenObserve\Handlers\ReportsException;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;
use Throwable;

class ExceptionReportingTest extends TestCase
{
    use ApiResponsePayload;

    public function test_reports_exceptions_to_open_observe_when_registered_with_the_exceptions_configuration(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->registerExceptionReporter();

        $exception = new RuntimeException('Something went wrong', 42);

        report($exception);

        Http::assertSent(function (Request $request) use ($exception): bool {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/test-org/exceptions/_json'
                && $request->header('Authorization') === ['Basic '.base64_encode('user:secret')]
                && $payload['message'] === 'Something went wrong'
                && $payload['code'] === 42
                && $payload['file'] === $exception->getFile()
                && $payload['line'] === $exception->getLine()
                && $payload['stack_trace'] === $exception->getTraceAsString()
                && is_string($payload['timestamp'])
                && $payload['timestamp'] !== '';
        });

        Http::assertSentCount(1);
    }

    public function test_does_not_report_exceptions_when_reporting_is_disabled(): void
    {
        Http::fake();

        $this->registerExceptionReporter(['enabled' => false]);

        report(new RuntimeException('Something went wrong'));

        Http::assertNothingSent();
    }

    public function test_reports_only_whitelisted_exceptions(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->registerExceptionReporter([
            'should_report' => [
                'whitelist' => [InvalidArgumentException::class],
            ],
        ]);

        report(new RuntimeException('Not whitelisted'));

        Http::assertNothingSent();

        report(new InvalidArgumentException('Whitelisted'));

        Http::assertSentCount(1);
    }

    public function test_does_not_report_blacklisted_exceptions(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->registerExceptionReporter([
            'should_report' => [
                'blacklist' => [InvalidArgumentException::class],
            ],
        ]);

        report(new InvalidArgumentException('Blacklisted'));

        Http::assertNothingSent();

        report(new RuntimeException('Not blacklisted'));

        Http::assertSentCount(1);
    }

    public function test_reports_only_exceptions_matching_the_should_report_callback(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->registerExceptionReporter([
            'should_report' => fn (Throwable $exception): bool => $exception->getMessage() === 'Report me',
        ]);

        report(new RuntimeException('Ignore me'));

        Http::assertNothingSent();

        report(new RuntimeException('Report me'));

        Http::assertSentCount(1);
    }

    /**
     * Register the OpenObserve exception reporter using Laravel's exception configuration.
     */
    private function registerExceptionReporter(array $overrides = []): void
    {
        $exceptions = new Exceptions($this->app->make(ExceptionHandler::class));

        $config = array_replace_recursive([
            'enabled' => true,
            'base_url' => 'https://openobserve.test',
            'organization' => 'test-org',
            'stream' => 'exceptions',
            'auth' => [
                'username' => 'user',
                'password' => 'secret',
            ],
            'http_options' => [
                'timeout' => 5,
                'connect_timeout' => 5,
                'verify_response' => true,
            ],
            'should_report' => null,
        ], $overrides);

        $exceptions->report(ReportsException::create($config));
    }
}
