<?php

namespace App\Http\Controllers\Mcp\BlogCategory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBlogCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpsertController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBlogCategoryService $service) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()->id;
        $dryRun  = $request->boolean('dry_run');

        $result = $this->service->upsert($slug, $request->all(), $tokenId, $dryRun);

        return $this->success(
            data: $result['data'],
            message: $dryRun ? 'Dry run — no changes written.' : 'Blog category saved.',
        );
    }
}

