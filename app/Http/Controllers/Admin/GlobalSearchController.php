<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * GET /admin/search?q=...&limit=5
     * Returns JSON for autocomplete widgets.
     */
    public function search(Request $request, GlobalSearchService $search): JsonResponse
    {
        $q     = (string) $request->input('q', '');
        $limit = max(1, min(20, (int) $request->input('limit', 5)));

        $results = $search->search($q, $limit);

        $total = collect($results)->sum(fn($arr) => is_array($arr) ? count($arr) : 0);

        return response()->json([
            'query'   => $q,
            'total'   => $total,
            'results' => $results,
        ]);
    }
}
