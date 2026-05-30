<?php

namespace App\Http\Controllers\Mcp\Batch;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpBatchService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data   = $request->all();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = $this->service->translate($data);

        return $this->success(
            data: $result['data'],
            message: $dryRun ? 'Dry run — no changes written.' : 'Batch translate completed.',
        );
    }
}
