<?php

namespace App\Http\Controllers;

use App\Services\ApiDocumentationService;
use Illuminate\Http\JsonResponse;

class ApiDocsController extends Controller
{
    public function __construct(private ApiDocumentationService $docs)
    {
    }

    /**
     * Swagger UI documentation page.
     */
    public function index()
    {
        return view('api-docs.swagger');
    }

    /**
     * Redoc documentation page (alternative).
     */
    public function redoc()
    {
        return view('api-docs.redoc');
    }

    /**
     * OpenAPI 3.0 JSON specification.
     */
    public function spec(): JsonResponse
    {
        return response()->json(
            $this->docs->build(),
            200,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * Authentication & usage guide page.
     */
    public function guide()
    {
        return view('api-docs.guide');
    }

    /**
     * Webhooks documentation with examples.
     */
    public function webhooks()
    {
        return view('api-docs.webhooks');
    }
}
