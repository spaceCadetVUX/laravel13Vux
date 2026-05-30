<?php

namespace App\Http\Controllers\Mcp\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpsertController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpProductService $service) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $tokenId = $request->user()->currentAccessToken()->id;
        $dryRun  = $request->boolean('dry_run');

        $result = $this->service->upsert($slug, $request->all(), $tokenId, $dryRun);

        return $this->success(
            data: $result['data'],
            message: $dryRun ? 'Dry run — no changes written.' : 'Product saved.',
            meta: isset($result['auto_created']) ? ['auto_created' => $result['auto_created']] : [],
        );
    }
}
