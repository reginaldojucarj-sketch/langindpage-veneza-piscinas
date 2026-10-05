<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\LegacyPostRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicPostController extends Controller
{
    public function __construct(private readonly LegacyPostRepository $posts) {}

    public function index(): JsonResponse
    {
        return $this->respond(fn () => $this->posts->published());
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(fn () => $this->posts->findPublished($id));
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            if (config('database.default') === 'mysql' && ! config('database.connections.mysql.host')) {
                $response = response()->json(['message' => 'Base de dados não configurada.'], 503);
            } else {
                $data = $callback();
                $response = $data === null
                    ? response()->json(['message' => 'Artigo não encontrado.'], 404)
                    : response()->json(['data' => $data]);
            }
        } catch (Throwable $error) {
            Log::error('Falha ao consultar os artigos públicos.', ['exception' => $error]);
            $response = response()->json(['message' => 'Artigos temporariamente indisponíveis.'], 503);
        }

        return $response;
    }
}
