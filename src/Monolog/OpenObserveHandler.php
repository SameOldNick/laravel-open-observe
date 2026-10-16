<?php

namespace SameOldNick\OpenObserve\Monolog;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Log\Formatters\JsonFormatter;
use Illuminate\Support\Arr;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use SameOldNick\OpenObserve\Support\OpenObserveClient;

class OpenObserveHandler extends AbstractProcessingHandler
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly OpenObserveClient $client,
        public readonly string $stream,
        public readonly bool $verifyResponse = true,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->setFormatter($this->createFormatter());
    }

    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records): void
    {
        $batch = $this->getFormatter()->formatBatch($records);

        try {
            $response = $this->client->sendMultiLogs($this->stream, $batch);

            if ($this->verifyResponse) {
                $this->handleResponse($response);
            }
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        } catch (ConnectionException $e) {
            $this->handleConnectionException($e);
        }
    }

    /**
     * Writes the (already formatted) record down to the log of the implementing handler
     */
    protected function write(LogRecord $record): void
    {
        $logData = $this->getFormatter()->format($record);

        try {
            $response = $this->client->sendSingleLog($this->stream, $logData);

            if ($this->verifyResponse) {
                $this->handleResponse($response);
            }
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        } catch (ConnectionException $e) {
            $this->handleConnectionException($e);
        }
    }

    /**
     * Handles the response from the OpenObserve API.
     *
     * @param  Response  $response  The response from the OpenObserve API
     *
     * @throws RuntimeException If the response indicates a failure
     */
    protected function handleResponse(Response $response): void
    {
        if ($response->json('code') != 200) {
            throw new RuntimeException('Failed to send log to OpenObserve: '.$response->body());
        }

        $statuses = $response->json('status');

        if (is_array($statuses)) {
            foreach ($statuses as $status) {
                if (! Arr::hasAll($status, ['name', 'successful', 'failed'])) {
                    throw new RuntimeException('Failed to send log to OpenObserve: Invalid response format');
                }

                if ($status['failed'] > 0) {
                    throw new RuntimeException('Failed to send log to OpenObserve: '.$response->body());
                }
            }
        } else {
            throw new RuntimeException('Failed to send log to OpenObserve: Invalid response format');
        }
    }

    /**
     * Handles a request exception when sending logs to OpenObserve.
     *
     * @param  RequestException  $e  The request exception
     *
     * @throws RuntimeException Always throws a runtime exception with the error message
     */
    protected function handleRequestException(RequestException $e): void
    {
        throw new RuntimeException('Failed to send log to OpenObserve: '.$e->getMessage(), 0, $e);
    }

    /**
     * Handles a connection exception when sending logs to OpenObserve.
     *
     * @param  ConnectionException  $e  The connection exception
     *
     * @throws RuntimeException Always throws a runtime exception with the error message
     */
    protected function handleConnectionException(ConnectionException $e): void
    {
        throw new RuntimeException('Failed to connect to OpenObserve: '.$e->getMessage(), 0, $e);
    }

    /**
     * Creates a formatter for the log records.
     *
     * @return FormatterInterface The formatter instance
     */
    protected function createFormatter(): FormatterInterface
    {
        return new JsonFormatter(batchMode: JsonFormatter::BATCH_MODE_NEWLINES, appendNewline: true);
    }
}
