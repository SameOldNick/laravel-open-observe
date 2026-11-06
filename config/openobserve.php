<?php

return [
    'exceptions' => [
        'enabled' => env('OPENOBSERVE_EXCEPTIONS_ENABLED', true),
        'name' => env('OPENOBSERVE_LOGGER_NAME', 'openobserve'),
        'base_url' => env('OPENOBSERVE_URL', ''),
        'organization' => env('OPENOBSERVE_ORGANIZATION', 'default'),

        /*
        |--------------------------------------------------------------------------
        | OpenObserve Stream
        |--------------------------------------------------------------------------
        |
        | The stream to which exceptions will be sent.
        |
        */
        'stream' => env('OPENOBSERVE_EXCEPTIONS_STREAM', 'exceptions'),

        'auth' => [
            // Alternatively, paste the Authorization header value from an ingest token.
            // When set, 'token' takes precedence over the username and password.
            // 'token' => env('OPENOBSERVE_TOKEN'),
            'username' => env('OPENOBSERVE_USERNAME'),
            'password' => env('OPENOBSERVE_PASSWORD'),
        ],
        'http_options' => [
            'timeout' => env('OPENOBSERVE_HTTP_TIMEOUT', 5),
            'connect_timeout' => env('OPENOBSERVE_HTTP_CONNECT_TIMEOUT', 5),
            'verify_response' => env('OPENOBSERVE_VERIFY_RESPONSE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Should Report Closure
        |--------------------------------------------------------------------------
        |
        | An array of 'whitelist' and/or 'blacklist' exception class names, or a closure that receives the exception and returns a boolean indicating whether it should be reported.
        |
        | Example:
        | 'should_report' => [
        |     'whitelist' => [
        |         \App\Exceptions\MyCustomException::class,
        |     ],
        |     'blacklist' => [
        |         \App\Exceptions\AnotherException::class,
        |     ],
        | ],
        |
        | 'should_report' => \App\Services\OpenObserve\ShouldReportExceptionClosure::class,
        */
        'should_report' => null,

        /*
        |--------------------------------------------------------------------------
        | Transform Exception Closure
        |--------------------------------------------------------------------------
        |
        | A closure that receives the exception and returns a string representation of the exception to be sent to OpenObserve.
        | If not set, the default transformation will be used.
        |
        | Example:
        | 'transform_exception' => \App\Services\OpenObserve\TransformExceptionClosure::class,
        */
        'transform_exception' => null,
    ],
];
