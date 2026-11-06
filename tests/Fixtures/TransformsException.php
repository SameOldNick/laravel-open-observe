<?php

namespace SameOldNick\OpenObserve\Tests\Fixtures;

use Throwable;

class TransformsException
{
    public function __invoke(Throwable $exception): string
    {
        return json_encode([
            'transformed_by' => 'invokable',
            'message' => $exception->getMessage(),
        ]);
    }
}
