<?php

namespace SameOldNick\OpenObserve\Tests\Concerns;

trait ApiResponsePayload
{
    /**
     * Returns a sample API response payload.
     */
    private function apiResponsePayload(int $code = 200, ?array $statuses = null): array
    {
        return [
            'code' => $code,
            'status' => $statuses ?? [
                [
                    'name' => 'application',
                    'successful' => 1,
                    'failed' => 0,
                ],
            ],
        ];
    }
}
