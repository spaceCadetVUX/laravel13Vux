<?php

namespace App\Http\Controllers\Mcp\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpsertController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpCategoryService $service) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()->id;
        $dryRun  = filter_var($request->query('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $result = $this->service->upsert($slug, $request->all(), $tokenId, $dryRun);

        return $this->success(
            data: $result['data'],
            message: $dryRun ? 'Dry run — no changes written.' : 'Category saved.',
        );
    }
}
