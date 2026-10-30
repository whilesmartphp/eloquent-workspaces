<?php

namespace Tests\Feature;

use Illuminate\Http\JsonResponse;
use Tests\TestCase;
use Whilesmart\Workspaces\Contracts\ResponseFormatter;

class ResponseFormatterTest extends TestCase
{
    public function test_default_formatter_preserves_the_package_response(): void
    {
        $this->getJson('/workspaces')->assertUnauthorized()->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_missing_formatter_configuration_uses_the_default_response(): void
    {
        $settings = config('workspaces');
        unset($settings['response_formatter']);
        config(['workspaces' => $settings]);

        $this->assertFalse(config()->has('workspaces.response_formatter'));
        $this->getJson('/workspaces')->assertUnauthorized()->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_host_formatter_receives_the_payload_and_http_status(): void
    {
        config(['workspaces.response_formatter' => HostResponseFormatter::class]);
        $this->getJson('/workspaces')->assertUnauthorized()->assertExactJson([
            'success' => false, 'message' => 'Unauthorized', 'errors' => [],
        ]);
    }
}

class HostResponseFormatter implements ResponseFormatter
{
    public function format(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(['success' => $status < 400,
            'message' => $payload['message'] ?? $payload['error'] ?? 'Operation successful',
            'errors' => $payload['errors'] ?? []], $status);
    }
}
