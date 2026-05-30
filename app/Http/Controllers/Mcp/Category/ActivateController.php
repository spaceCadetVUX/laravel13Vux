<?php

namespace App\Http\Controllers\Mcp\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpCategoryService;
use Illuminate\Http\JsonResponse;

class ActivateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpCategoryService $service) {}

    public function __invoke(string $slug): JsonResponse
    {
        $result = $this->service->activate($slug);

        return $this->success(data: $result['data'], message: 'Category activated.');
    }
}
