<?php

namespace Whilesmart\Workspaces\Contracts;

use Illuminate\Http\JsonResponse;

interface ResponseFormatter
{
    public function format(array $payload, int $status = 200): JsonResponse;
}
