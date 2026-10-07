<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\EditorialUnavailable;
use App\Exceptions\OrderConflict;
use App\Http\Controllers\Controller;
use App\Repositories\LegacyPostRepository;
use App\Services\EditorialOrdering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PostOrderController extends Controller
{
    public function __construct(
        private readonly LegacyPostRepository $posts,
        private readonly EditorialOrdering $ordering,
    ) {}

    public function index(): View
    {
        $listing = $this->posts->publishedWithRevision();

        return view('admin.post-order', [
            'posts' => $listing['posts'],
            'canSave' => $this->ordering->isAvailable(),
            'revision' => $listing['revision'],
            'idempotencyKey' => (string) Str::uuid(),
            'conflictMessage' => null,
            'correlationId' => null,
        ]);
    }

    public function update(Request $request): RedirectResponse|Response|JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $submitted = array_map('intval', $data['ids']);
        $correlationId = (string) Str::uuid();

        try {
            $result = $this->ordering->save(
                $submitted,
                (int) $data['expected_revision'],
                (int) $request->user()->getAuthIdentifier(),
                $data['idempotency_key'],
                $correlationId,
            );
        } catch (OrderConflict) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'A ordem mudou enquanto você editava. Revise a versão atual antes de tentar novamente.',
                    'current_revision' => $this->ordering->revision(),
                    'submitted_ids' => $submitted,
                    'correlation_id' => $correlationId,
                ], 409)->header('X-Correlation-ID', $correlationId);
            }

            $listing = $this->posts->publishedWithRevision();
            $currentPosts = $listing['posts'];
            $postsById = [];
            foreach ($currentPosts as $post) {
                $postsById[(int) $post['id']] = $post;
            }

            $recoveredPosts = [];
            $seenIds = [];
            $unavailableDraftIds = [];
            foreach ($submitted as $postId) {
                if (isset($postsById[$postId])) {
                    $recoveredPosts[] = $postsById[$postId];
                    $seenIds[$postId] = true;
                } else {
                    $unavailableDraftIds[] = $postId;
                }
            }
            foreach ($currentPosts as $post) {
                $postId = (int) $post['id'];
                if (! isset($seenIds[$postId])) {
                    $recoveredPosts[] = $post;
                }
            }

            return response()->view('admin.post-order', [
                'posts' => $recoveredPosts,
                'canSave' => $this->ordering->isAvailable(),
                'revision' => $listing['revision'],
                'idempotencyKey' => (string) Str::uuid(),
                'unavailableDraftIds' => $unavailableDraftIds,
                'conflictMessage' => 'Seu rascunho foi reaplicado à lista atual. Os artigos que seguem publicados mantêm a ordem escolhida; novos artigos publicados foram acrescentados ao final. Revise a lista e salve novamente.',
                'correlationId' => $correlationId,
            ], 409)->header('X-Correlation-ID', $correlationId);
        } catch (EditorialUnavailable) {
            return $this->unavailable($request, $correlationId);
        } catch (Throwable $error) {
            Log::error('Falha ao salvar a ordem editorial.', [
                'correlation_id' => $correlationId,
                'exception_type' => $error::class,
            ]);

            return $this->unavailable($request, $correlationId);
        }

        return redirect()->route('admin.posts.order.index')
            ->with('status', $result['replayed']
                ? "Solicitação repetida com segurança; a revisão {$result['revision']} já foi aplicada."
                : "Ordem de {$result['count']} artigos salva na revisão {$result['revision']}.")
            ->header('X-Correlation-ID', $correlationId);
    }

    private function unavailable(Request $request, string $correlationId): Response|JsonResponse
    {
        $message = 'A ordenação está temporariamente indisponível. Nenhuma alteração foi confirmada.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'correlation_id' => $correlationId], 503)
                ->header('X-Correlation-ID', $correlationId);
        }

        return response()->view('errors.503', ['exception' => null, 'correlationId' => $correlationId], 503)
            ->header('X-Correlation-ID', $correlationId);
    }
}
