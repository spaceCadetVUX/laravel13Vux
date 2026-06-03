<?php

namespace App\Http\Controllers\Mcp\BlogPost;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBlogPostService;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBlogPostService $service) {}

    public function __invoke(string $slug): JsonResponse
    {
        return $this->success(data: $this->service->readiness($slug));
    }
}
