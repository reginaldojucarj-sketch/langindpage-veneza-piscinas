<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ApiDocumentationController extends Controller
{
    public function show(): View
    {
        return view('api.docs');
    }

    public function specification(): JsonResponse
    {
        $specification = require resource_path('openapi/admin-v1.php');

        return response()->json($specification)->header('Cache-Control', 'no-store, private');
    }
}
