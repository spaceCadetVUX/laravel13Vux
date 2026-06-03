<?php

namespace App\Http\Controllers\Mcp\BlogCategory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBlogCategoryService;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBlogCategoryService $service) {}

    public function __invoke(string $slug): JsonResponse
    {
        return $this->success(data: $this->service->readiness($slug));
    }
}
