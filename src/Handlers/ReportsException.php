<?php

namespace SameOldNick\OpenObserve\Handlers;

use Closure;
use SameOldNick\OpenObserve\Factories\CreateOpenObserveClient;
use SameOldNick\OpenObserve\Support\OpenObserveClient;
use Throwable;

class ReportsException
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly ?OpenObserveClient $client,
        public readonly string $stream,
        public readonly ?Closure $shouldReportClosure = null,
        public readonly ?Closure $transformExceptionClosure = null,
    ) {
        //
    }

    /**
     * Determine if the exception should be reported to OpenObserve.
     */
    public function shouldReport(Throwable $exception): bool
    {
        if ($this->shouldReportClosure) {
            return ($this->shouldReportClosure)($exception);
        }

        return true;
    }

    /**
     * Handle the exception and report it to OpenObserve.
     *
     * @return void
     */
    public function report(Throwable $exception)
    {
        if ($this->shouldReport($exception)) {
            $this->sendToOpenObserve($exception);
        }
    }

    /**
     * Send the exception to OpenObserve.
     *
     * @return void
     */
    protected function sendToOpenObserve(Throwable $exception)
    {
        if (! $this->client) {
            logger()->warning('OpenObserve client is not configured. Exception not sent.');

            return;
        }

        try {
            $record = $this->transformExceptionForSend($exception);

            $this->client->sendSingleLog($this->stream, $record);
        } catch (Throwable $e) {
            logger()->error('Failed to send exception to OpenObserve: '.$e->getMessage());
        }
    }

    /**
     * Transform the exception into a format suitable for sending to OpenObserve.
     */
    protected function transformExceptionForSend(Throwable $exception): string
    {
        if ($this->transformExceptionClosure) {
            return ($this->transformExceptionClosure)($exception);
        }

        $record = [
            'message' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => now()->toIso8601String(),
        ];

        return json_encode($record);
    }

    /**
     * Invoke the class instance.
     */
    public function __invoke(Throwable $exception)
    {
        $this->report($exception);
    }

    /**
     * Creates from config
     */
    public static function create(array $config): self
    {
        if (! $config['enabled']) {
            return new self(
                client: null,
                stream: $config['stream'],
                shouldReportClosure: fn (Throwable $exception) => false,
            );
        }

        $clientFactory = app(CreateOpenObserveClient::class);
        $client = $clientFactory->build($config);

        $shouldReportCallback = $config['should_report'] ? function (Throwable $exception) use ($config) {
            if (is_array($config['should_report'])) {
                $exceptionClass = get_class($exception);

                if (! empty($config['should_report']['whitelist'])) {
                    return in_array($exceptionClass, $config['should_report']['whitelist'], true);
                }

                if (! empty($config['should_report']['blacklist'])) {
                    return ! in_array($exceptionClass, $config['should_report']['blacklist'], true);
                }
            }

            return app()->call($config['should_report'], ['exception' => $exception]);
        } : null;

        $transformExceptionCallback = $config['transform_exception'] ? function (Throwable $exception) use ($config) {
            return app()->call($config['transform_exception'], ['exception' => $exception]);
        } : null;

        return new self(
            $client,
            $config['stream'],
            $shouldReportCallback ? Closure::fromCallable($shouldReportCallback) : null,
            $transformExceptionCallback ? Closure::fromCallable($transformExceptionCallback) : null,
        );
    }
}
