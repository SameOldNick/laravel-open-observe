<?php

namespace SameOldNick\OpenObserve\Tests\Unit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use SameOldNick\OpenObserve\DTOs\ApiResponse;
use SameOldNick\OpenObserve\Exceptions\InvalidResponse;
use SameOldNick\OpenObserve\Support\ObservesOpenObserveClient;
use SameOldNick\OpenObserve\Tests\Concerns\ApiResponsePayload;
use SameOldNick\OpenObserve\Tests\TestCase;

class OpenObserveClientTest extends TestCase
{
    use ApiResponsePayload;

    public function test_builds_api_endpoint_path_with_a_single_leading_slash(): void
    {
        $client = $this->makeClient();

        $this->assertSame('/api/default/logs/_json', $client->buildApiEndpointPath('/logs/_json'));
        $this->assertSame('/api/default/logs/_json', $client->buildApiEndpointPath('logs/_json'));
    }

    public function test_sends_a_single_log_to_the_json_endpoint(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $client = $this->makeClient();
        $response = $client->sendSingleLog('application', '{"message":"single"}');

        $this->assertInstanceOf(ApiResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(200, $response->code);
        $this->assertSame(1, $response->getSuccessfulCount());
        $this->assertSame(0, $response->getFailedCount());
        $this->assertSame('application', $response->getStatusForStream('application')?->stream);
        $this->assertSame(200, $response->originalResponse?->status());
        $this->assertCount(1, $client->getRequests());
        $this->assertCount(1, $client->getResponses());
        $this->assertSame(200, $client->getResponses()[0]->getStatusCode());

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/default/application/_json'
                && $request->body() === '{"message":"single"}'
                && $request->header('Content-Type') === ['application/json']
                && $request->header('Accept') === ['application/json']
                && $request->header('Authorization') === ['Basic '.base64_encode('user:secret')];
        });
    }

    public function test_sends_multiple_logs_to_the_multi_endpoint(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $client = $this->makeClient();
        $response = $client->sendMultiLogs('application', "{\"message\":\"first\"}\n{\"message\":\"second\"}\n");

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(1, $response->getSuccessfulCount());
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://openobserve.test/api/default/application/_multi'
                && $request->body() === "{\"message\":\"first\"}\n{\"message\":\"second\"}\n";
        });
    }

    public function test_executes_request_and_response_callbacks(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(201), 201),
        ]);

        $requests = [];
        $responses = [];
        $client = $this->makeClient()
            ->setOnRequestCallback(function ($request) use (&$requests): void {
                $requests[] = $request;
            })
            ->setOnResponseCallback(function ($response) use (&$responses): void {
                $responses[] = $response;
            });

        $response = $client->sendSingleLog('application', '{"message":"callback"}');

        $this->assertSame(201, $response->code);
        $this->assertSame(201, $response->originalResponse?->status());
        $this->assertCount(1, $requests);
        $this->assertCount(1, $responses);
        $this->assertSame($requests[0], $client->getRequests()[0]);
        $this->assertSame($responses[0], $client->getResponses()[0]);
        $this->assertSame(201, $responses[0]->getStatusCode());
    }

    public function test_builds_api_endpoint_path_ignoring_extra_leading_slashes(): void
    {
        $this->assertSame('/api/default/logs/_json', $this->makeClient()->buildApiEndpointPath('///logs/_json'));
    }

    public function test_builds_api_endpoint_path_when_the_suffix_is_empty(): void
    {
        $this->assertSame('/api/default/', $this->makeClient()->buildApiEndpointPath(''));
    }

    public function test_builds_api_endpoint_path_using_the_configured_organization(): void
    {
        $this->assertSame('/api/my-org/logs/_json', $this->makeClient(organization: 'my-org')->buildApiEndpointPath('/logs/_json'));
    }

    public function test_omits_authorization_header_when_no_credentials_are_configured(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(username: null, password: null)
            ->sendSingleLog('application', '{"message":"anonymous"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === []);
    }

    public function test_sends_basic_auth_when_only_the_username_is_configured(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(password: null)
            ->sendSingleLog('application', '{"message":"username only"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Basic '.base64_encode('user:')]);
    }

    public function test_sends_basic_auth_when_only_the_password_is_configured(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(username: null)
            ->sendSingleLog('application', '{"message":"password only"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Basic '.base64_encode(':secret')]);
    }

    public function test_sends_the_configured_token_as_the_authorization_header(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $token = 'Basic '.base64_encode('user:secret');

        $this->makeClient(token: $token, username: null, password: null)
            ->sendSingleLog('application', '{"message":"token"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === [$token]);
    }

    public function test_sends_the_token_without_adding_a_scheme(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $token = base64_encode('user:secret');

        $this->makeClient(token: $token, username: null, password: null)
            ->sendSingleLog('application', '{"message":"token without scheme"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === [$token]);
    }

    public function test_prefers_the_token_over_the_configured_username_and_password(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(token: 'Bearer ingest-token')
            ->sendSingleLog('application', '{"message":"token precedence"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer ingest-token']);
    }

    public function test_falls_back_to_basic_auth_when_the_token_is_blank(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(token: '   ')
            ->sendSingleLog('application', '{"message":"blank token"}');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Basic '.base64_encode('user:secret')]);
    }

    public function test_applies_configured_http_options_to_the_request(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(options: ['headers' => ['X-Custom-Header' => 'custom']])
            ->sendSingleLog('application', '{"message":"options"}');

        Http::assertSent(fn (Request $request): bool => $request->header('X-Custom-Header') === ['custom']);
    }

    public function test_does_not_produce_a_double_slash_when_the_base_url_has_a_trailing_slash(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient(baseUrl: 'https://openobserve.test/')
            ->sendSingleLog('application', '{"message":"trailing slash"}');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/default/application/_json');
    }

    public function test_preserves_the_base_url_path_when_building_the_request_url(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $client = $this->makeClient(baseUrl: 'https://openobserve.test/openobserve');
        $client->sendSingleLog('application', '{"message":"subpath"}');

        $this->assertSame(
            'https://openobserve.test/openobserve/api/default/application/_json',
            (string) $client->getRequests()[0]->getUri(),
        );
    }

    public function test_throws_invalid_argument_exception_when_the_stream_is_empty(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->makeClient()->sendSingleLog('', '{"message":"no stream"}');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/default/_json');
    }

    public function test_preserves_surrounding_slashes_in_the_stream(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $this->makeClient()->sendSingleLog('/application/', '{"message":"slashes"}');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openobserve.test/api/default/application//_json');
    }

    public function test_sends_log_data_containing_utf8_characters_unchanged(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $logData = '{"message":"こんにちは 🚀"}';

        $this->makeClient()->sendSingleLog('application', $logData);

        Http::assertSent(fn (Request $request): bool => $request->body() === $logData);
    }

    public function test_records_each_request_when_multiple_logs_are_sent(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $client = $this->makeClient();
        $client->sendSingleLog('application', '{"message":"first"}');
        $client->sendMultiLogs('application', "{\"message\":\"second\"}\n");

        $this->assertCount(2, $client->getRequests());
        $this->assertCount(2, $client->getResponses());
        $this->assertSame('https://openobserve.test/api/default/application/_json', (string) $client->getRequests()[0]->getUri());
        $this->assertSame('https://openobserve.test/api/default/application/_multi', (string) $client->getRequests()[1]->getUri());
    }

    public function test_stops_invoking_callbacks_after_they_are_cleared(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload()),
        ]);

        $invocations = 0;
        $client = $this->makeClient()->setOnRequestCallback(function () use (&$invocations): void {
            $invocations++;
        });

        $client->sendSingleLog('application', '{"message":"first"}');
        $client->setOnRequestCallback(null);
        $client->sendSingleLog('application', '{"message":"second"}');

        $this->assertSame(1, $invocations);
        $this->assertCount(2, $client->getRequests());
    }

    public function test_reports_failed_entries_in_the_api_response(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [
                ['name' => 'application', 'successful' => 1, 'failed' => 2],
            ])),
        ]);

        $response = $this->makeClient()->sendMultiLogs('application', "{\"message\":\"first\"}\n{\"message\":\"second\"}\n");

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(1, $response->getSuccessfulCount());
        $this->assertSame(2, $response->getFailedCount());
    }

    public function test_totals_counts_across_multiple_streams(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [
                ['name' => 'application', 'successful' => 2, 'failed' => 0],
                ['name' => 'audit', 'successful' => 3, 'failed' => 1],
            ])),
        ]);

        $response = $this->makeClient()->sendMultiLogs('application', "{\"message\":\"first\"}\n");

        $this->assertSame(5, $response->getSuccessfulCount());
        $this->assertSame(1, $response->getFailedCount());
        $this->assertSame(3, $response->getStatusForStream('audit')?->successCount);
        $this->assertNull($response->getStatusForStream('missing'));
    }

    public function test_reports_success_when_the_status_list_is_empty(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(statuses: [])),
        ]);

        $response = $this->makeClient()->sendSingleLog('application', '{"message":"empty status"}');

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(0, $response->getSuccessfulCount());
        $this->assertSame(0, $response->getFailedCount());
        $this->assertEmpty($response->statuses);
    }

    public function test_reports_a_client_error_when_the_api_code_indicates_failure(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(code: 400)),
        ]);

        $response = $this->makeClient()->sendSingleLog('application', '{"message":"bad request"}');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isServerError());
        $this->assertSame(400, $response->code);
        $this->assertSame(200, $response->originalResponse?->status());
    }

    public function test_reports_a_server_error_when_the_api_code_indicates_failure(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(code: 500)),
        ]);

        $response = $this->makeClient()->sendSingleLog('application', '{"message":"server error"}');

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isClientError());
        $this->assertTrue($response->isServerError());
    }

    public function test_reports_success_when_status_is_missing(): void
    {
        Http::fake([
            '*' => Http::response(['code' => 200]),
        ]);

        $response = $this->makeClient()->sendSingleLog('application', '{"message":"missing status"}');

        $this->assertTrue($response->isSuccessful());
        $this->assertEmpty($response->statuses);
    }

    public function test_throws_invalid_response_exception_when_a_status_entry_is_incomplete(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 200,
                'status' => [['name' => 'application']],
            ]),
        ]);

        $this->expectException(InvalidResponse::class);

        $this->makeClient()->sendSingleLog('application', '{"message":"incomplete status"}');
    }

    public function test_throws_when_the_response_body_is_not_json(): void
    {
        Http::fake([
            '*' => Http::response('Service Unavailable'),
        ]);

        $this->expectException(RuntimeException::class);

        $this->makeClient()->sendSingleLog('application', '{"message":"non json body"}');
    }

    public function test_throws_request_exception_when_the_api_returns_an_error_status(): void
    {
        Http::fake([
            '*' => Http::response($this->apiResponsePayload(), 401),
        ]);

        $this->expectException(RequestException::class);

        $this->makeClient()->sendSingleLog('application', '{"message":"unauthorized"}');
    }

    public function test_propagates_connection_exceptions(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(ConnectionException::class);

        $this->makeClient()->sendSingleLog('application', '{"message":"offline"}');
    }

    private function makeClient(
        string $baseUrl = 'https://openobserve.test',
        string $organization = 'default',
        ?string $username = 'user',
        ?string $password = 'secret',
        ?array $options = null,
        ?string $token = null,
    ): ObservesOpenObserveClient {
        return new ObservesOpenObserveClient(
            baseUrl: $baseUrl,
            organization: $organization,
            username: $username,
            password: $password,
            options: $options,
            token: $token,
        );
    }
}
