<?php

namespace SameOldNick\OpenObserve\Factories;

use Illuminate\Support\Arr;
use SameOldNick\OpenObserve\Support\OpenObserveClient;

class CreateOpenObserveClient
{
    public function __construct()
    {
        //
    }

    /**
     * Build a new OpenObserve client instance.
     */
    public function build(array $config): OpenObserveClient
    {
        $token = Arr::get($config, 'auth.token');

        return new OpenObserveClient(
            baseUrl: Arr::get($config, 'base_url', ''),
            organization: Arr::get($config, 'organization', ''),
            username: Arr::get($config, 'auth.username'),
            password: Arr::get($config, 'auth.password'),
            options: Arr::get($config, 'http_options'),
            token: is_string($token) && $token !== '' ? $token : null,
        );
    }

    /**
     * Invoke the class instance.
     */
    public function __invoke(array $config): OpenObserveClient
    {
        return $this->build($config);
    }
}
