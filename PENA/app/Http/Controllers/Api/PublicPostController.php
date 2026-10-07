<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PublishedListingChanged;
use App\Http\Controllers\Controller;
use App\Repositories\LegacyPostRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PublicPostController extends Controller
{
    public function __construct(private readonly LegacyPostRepository $posts) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        return $this->respond(
            fn () => $this->posts->publishedPage(
                (int) ($filters['page'] ?? 1),
                (int) ($filters['per_page'] ?? 50),
                isset($filters['q']) ? trim($filters['q']) : null,
            ),
            paginated: true,
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(fn () => $this->posts->findPublished($id));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        if (strlen($slug) > 200) {
            return response()->json(['message' => 'Artigo não encontrado.'], 404)
                ->header('Cache-Control', 'no-store, private');
        }

        return $this->respond(fn () => $this->posts->findPublishedBySlug($slug));
    }

    private function respond(callable $callback, bool $paginated = false): JsonResponse
    {
        $correlationId = (string) Str::uuid();

        try {
            if (config('database.default') === 'mysql' && ! config('database.connections.mysql.host')) {
                $response = response()->json(['message' => 'Base de dados não configurada.'], 503);
            } else {
                $data = $callback();
                if ($paginated) {
                    $response = response()->json($data);
                } else {
                    $response = $data === null
                        ? response()->json(['message' => 'Artigo não encontrado.'], 404)
                        : response()->json(['data' => $data]);
                }
            }
        } catch (PublishedListingChanged) {
            $response = response()->json([
                'message' => 'A lista de artigos mudou durante a consulta. Recarregue e tente novamente.',
            ], 409);
        } catch (Throwable $error) {
            Log::error('Falha ao consultar os artigos públicos.', [
                'correlation_id' => $correlationId,
                'exception_type' => $error::class,
                'sqlstate' => $error instanceof QueryException ? ($error->errorInfo[0] ?? null) : null,
            ]);
            $response = response()->json(['message' => 'Artigos temporariamente indisponíveis.'], 503);
        }

        return $response->header('X-Correlation-ID', $correlationId)
            ->header('Cache-Control', 'no-store, private');
    }
}
