<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewQueueController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpReviewQueueService $reviewQueueService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->reviewQueueService->queue($request->all());

        return $this->success(
            data: $result['data'],
            meta: array_merge($result['meta'], ['summary' => $result['summary']]),
        );
    }
}
