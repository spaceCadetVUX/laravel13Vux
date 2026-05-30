<?php

namespace App\Http\Controllers\Mcp\BlogPost;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBlogPostService $service) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $result = $this->service->publish($slug, $request->all());

        return $this->success(data: $result['data'], message: 'Blog post published.');
    }
}
