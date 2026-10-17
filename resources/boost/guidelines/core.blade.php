## Laravel Open Observe

This package registers an `openobserve` logging driver that sends Laravel and Monolog records to an OpenObserve instance.

### Configure the logging channel

Prefer the registered driver in `config/logging.php`:

@verbatim
<code-snippet name="Configure the OpenObserve logging channel" lang="php">
'openobserve' => [
    'driver' => 'openobserve',
    'name' => env('OPENOBSERVE_LOGGER_NAME', 'openobserve'),
    'base_url' => env('OPENOBSERVE_URL'),
    'organization' => env('OPENOBSERVE_ORGANIZATION', 'default'),
    'stream' => env('OPENOBSERVE_STREAM'),
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
</code-snippet>
@endverbatim

`base_url`, `organization`, and `stream` are required. The stream must be non-empty. Authentication is omitted when both credentials are empty.

`verify_response` must be placed inside `http_options`. `name` sets the channel field on each record, `level` sets the minimum Monolog level, and `bubble` controls whether records continue to subsequent Monolog handlers.

The legacy `custom` driver configuration remains supported:

@verbatim
<code-snippet name="Use the legacy custom logging driver" lang="php">
'driver' => 'custom',
'via' => \SameOldNick\OpenObserve\Factories\CreateOpenObserveLogger::class,
</code-snippet>
@endverbatim

### Log with the channel

@verbatim
<code-snippet name="Log through the OpenObserve channel" lang="php">
use Illuminate\Support\Facades\Log;

Log::channel('openobserve')->info('Order created.', ['order_id' => $order->id]);
</code-snippet>
@endverbatim

Single records are sent to `/api/{organization}/{stream}/_json`. Batched records use `/api/{organization}/{stream}/_multi`.

### Test the connection

@verbatim
<code-snippet name="Test the OpenObserve connection" lang="bash">
php artisan openobserve:test-connection
php artisan openobserve:test-connection --stream=my-stream --debug
</code-snippet>
@endverbatim

Use the connection tester from jobs or services:

@verbatim
<code-snippet name="Test OpenObserve from application code" lang="php">
$result = app(\SameOldNick\OpenObserve\Support\OpenObserveConnectionTester::class)
    ->test(config('logging.channels.openobserve'));

if (! $result->successful) {
    throw new \RuntimeException($result->message, previous: $result->exception);
}
</code-snippet>
@endverbatim

### Failure behavior

With `verify_response` enabled, a non-2xx response code or any failed entries throw a `RuntimeException`. HTTP and connection failures throw regardless of `verify_response`. Disable response verification only when body-level failures should be ignored.
