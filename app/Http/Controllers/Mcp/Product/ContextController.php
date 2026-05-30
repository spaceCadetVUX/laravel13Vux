<?php

namespace App\Http\Controllers\Mcp\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpProductService;
use Illuminate\Http\JsonResponse;

class ContextController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpProductService $service) {}

    public function __invoke(string $slug): JsonResponse
    {
        return $this->success(data: $this->service->context($slug));
    }
}
