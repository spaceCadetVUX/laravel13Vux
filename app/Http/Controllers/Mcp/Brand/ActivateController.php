<?php

namespace App\Http\Controllers\Mcp\Brand;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBrandService;
use Illuminate\Http\JsonResponse;

class ActivateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBrandService $service) {}

    public function __invoke(string $slug): JsonResponse
    {
        $result = $this->service->activate($slug);

        return $this->success(data: $result['data'], message: 'Brand activated.');
    }
}
