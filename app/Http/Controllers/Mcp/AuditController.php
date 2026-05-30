<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpAuditService $auditService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->auditService->audit($request->all());

        return $this->success(
            data: $result['data'],
            meta: array_merge($result['meta'], ['summary' => $result['summary']]),
        );
    }
}
