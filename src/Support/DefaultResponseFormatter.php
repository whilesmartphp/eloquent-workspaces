<?php

namespace Whilesmart\Workspaces\Support;

use Illuminate\Http\JsonResponse;
use Whilesmart\Workspaces\Contracts\ResponseFormatter;

class DefaultResponseFormatter implements ResponseFormatter
{
    public function format(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }
}
