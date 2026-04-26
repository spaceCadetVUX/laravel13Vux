<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchController extends Controller
{
    // ML-16: Meilisearch query with locale filter
    public function index(Request $request, string $locale): Response
    {
        $q = $request->query('q', '');
        return response("Search [{$q}] — {$locale}", 200);
    }
}
