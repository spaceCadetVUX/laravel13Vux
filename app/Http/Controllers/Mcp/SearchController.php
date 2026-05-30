<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Mcp\McpSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly McpSearchService $searchService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query   = (string) $request->query('q', '');
        $types   = $this->searchService->parseTypes($request->query('types'));
        $locale  = in_array($request->query('locale'), ['vi', 'en'], true)
            ? $request->query('locale')
            : 'vi';
        $perPage = min((int) $request->query('per_page', 10), 50);

        if (blank($query)) {
            return $this->error('Query parameter q is required', 422);
        }

        $result = $this->searchService->search($query, $types, $locale, $perPage);

        return $this->success(data: $result['data']);
    }
}
