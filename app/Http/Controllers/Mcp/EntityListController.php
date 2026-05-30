<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityListController extends Controller
{
    use ApiResponse;

    // Slug in URL → internal model type key
    private const TYPE_MAP = [
        'products'       => 'product',
        'categories'     => 'category',
        'blog-posts'     => 'blog_post',
        'blog-categories'=> 'blog_category',
        'brands'         => 'brand',
        'manufacturers'  => 'manufacturer',
    ];

    public function __construct(private readonly McpAuditService $auditService) {}

    public function __invoke(Request $request, string $modelType): JsonResponse
    {
        $internalType = self::TYPE_MAP[$modelType] ?? null;

        if (! $internalType) {
            return $this->error(
                message: "Invalid model type '{$modelType}'. Valid: " . implode(', ', array_keys(self::TYPE_MAP)),
                code: 404,
            );
        }

        $result = $this->auditService->entityList($internalType, $request->all());

        return $this->success(data: $result['data'], meta: $result['meta']);
    }
}
