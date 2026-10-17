# Laravel Open Observe

[![Tests](https://github.com/SameOldNick/laravel-open-observe/actions/workflows/tests.yml/badge.svg)](https://github.com/SameOldNick/laravel-open-observe/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/SameOldNick/laravel-open-observe/graph/badge.svg?token=SKnilZ9qcA)](https://codecov.io/gh/SameOldNick/laravel-open-observe)

A Laravel package for sending application logs to [OpenObserve](https://openobserve.ai/).

## Requirements

- PHP 8.4 or later
- Laravel 11, 12, or 13

## Installation

You can install the package via Composer:

```bash
composer require sameoldnick/laravel-open-observe
```

## Configuration

Add a new logging channel to `config/logging.php`:

```php
'openobserve' => [
    'driver' => 'openobserve',
    'name' => env('OPENOBSERVE_LOGGER_NAME', 'openobserve'),
    'base_url' => env('OPENOBSERVE_URL', ''),
    'organization' => env('OPENOBSERVE_ORGANIZATION', 'default'),
    'stream' => env('OPENOBSERVE_STREAM', ''),
    'auth' => [
        'username' => env('OPENOBSERVE_USERNAME'),
        'password' => env('OPENOBSERVE_PASSWORD'),
    ],
    'http_options' => [
        'timeout' => env('OPENOBSERVE_HTTP_TIMEOUT', 5),
        'connect_timeout' => env('OPENOBSERVE_HTTP_CONNECT_TIMEOUT', 5),
        'verify_response' => env('OPENOBSERVE_VERIFY_RESPONSE', true),
    ],
    'level' => env('OPENOBSERVE_LOG_LEVEL', 'debug'),
    'bubble' => true,
],
```

The package registers the `openobserve` logging driver. If you prefer Laravel's `custom` driver, you can still use `'driver' => 'custom'` with `'via' => \SameOldNick\OpenObserve\Factories\CreateOpenObserveLogger::class`.

### Configuration options

| Option | Required | Description |
| --- | --- | --- |
| `base_url` | Yes | The base URL of your OpenObserve instance, such as `https://openobserve.example.com`. |
| `organization` | Yes | The OpenObserve organization used in the API path. |
| `stream` | Yes | The default stream that log entries are written to. |
| `auth.username` / `auth.password` | No | Basic authentication credentials. Authentication is omitted when both are empty. |
| `http_options` | No | Options passed to Laravel's HTTP client, such as `timeout` and `connect_timeout`. |
| `http_options.verify_response` | No | Verifies the OpenObserve response body. Defaults to `true`. |
| `name` | No | The logger name included in each log record. Defaults to `openobserve`. |
| `level` | No | The minimum Monolog level to send. Defaults to `debug`. |
| `bubble` | No | Whether records may bubble to subsequent Monolog handlers. Defaults to `true`. |

Single log entries are sent to `/api/{organization}/{stream}/_json`; batched entries use `/api/{organization}/{stream}/_multi`.

### Response verification and failures

With `verify_response` enabled, a response containing a non-2xx `code` or any `failed` entries throws a `RuntimeException`. HTTP and connection failures also throw, regardless of `verify_response`. Disable response verification only if you want to ignore OpenObserve body-level failures while still surfacing transport errors.

## Usage

The `Log` facade or the `logs()` helper can be used to send messages to OpenObserve:

```php
use Illuminate\Support\Facades\Log;

Log::channel('openobserve')->info('This is a test log message.');
logs('openobserve')->info('Testing connection to OpenObserve.');
```

If OpenObserve is the default logging channel, you can log messages without specifying the channel:

```php
use Illuminate\Support\Facades\Log;

Log::info('This is a test log message.');
logs()->info('Testing connection to OpenObserve.');
logger()->info('Testing connection to OpenObserve.');
```

To send logs to OpenObserve alongside another channel, add it to your `stack` channel:

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['single', 'openobserve'],
    'ignore_exceptions' => false,
],
```

## Testing the connection

You can verify connectivity and authentication with:

```bash
php artisan openobserve:test-connection
```

The command sends a test entry to the configured stream. Available options:

```bash
# Use a different logging channel configuration
php artisan openobserve:test-connection --config-key=logging.channels.openobserve

# Send the test entry to a specific stream
php artisan openobserve:test-connection --stream=my-stream

# Print request and response details
php artisan openobserve:test-connection --debug
```

> **Note:** `--debug` prints request headers, including authentication credentials, and response details. Avoid capturing its output in shared or persistent logs.

### Programmatic usage

The connection test is available as a console-independent runner, so it can also be used from jobs or other services:

```php
use SameOldNick\OpenObserve\Support\OpenObserveConnectionTester;

$result = app(OpenObserveConnectionTester::class)->test(
    config: config('logging.channels.openobserve'),
);

if (! $result->successful) {
    throw new RuntimeException($result->message, previous: $result->exception);
}
```

The result exposes `successful`, `message`, `stream`, `response`, and `exception`.

## Development

```bash
composer install
composer test
composer lint
composer format
```

## Security

Please review [SECURITY.md](SECURITY.md) for the vulnerability reporting policy.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
