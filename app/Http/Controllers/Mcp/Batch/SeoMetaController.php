<?php

namespace App\Http\Controllers\Mcp\Batch;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoMetaController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBatchService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->service->seoMeta($request->all());

        return $this->success(
            data: $result['data'],
            message: "SEO meta batch: {$result['data']['filled']} filled, {$result['data']['skipped']} skipped.",
        );
    }
}
