<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EditorialUnavailable;
use App\Exceptions\OrderConflict;
use App\Exceptions\PostConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostContentRequest;
use App\Models\AdminUser;
use App\Models\EditorialAuthor;
use App\Models\EditorialMedia;
use App\Repositories\LegacyPostRepository;
use App\Services\AdminAccounts;
use App\Services\AuthorLibrary;
use App\Services\EditorialOrdering;
use App\Services\MediaLibrary;
use App\Services\PostEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Session/CSRF API for the first-party Swagger UI; never bypasses domain services. */
class AdminApiController extends Controller
{
    public function __construct(
        private readonly AdminAccounts $accounts,
        private readonly AuthorLibrary $authors,
        private readonly MediaLibrary $media,
        private readonly PostEditor $posts,
        private readonly LegacyPostRepository $legacyPosts,
        private readonly EditorialOrdering $ordering,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function users(): JsonResponse
    {
        return response()->json(['data' => AdminUser::query()->orderBy('name')->get()->map(fn ($user) => $this->userData($user))]);
    }

    public function user(int $user): JsonResponse
    {
        return response()->json(['data' => $this->userData(AdminUser::findOrFail($user))]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $input = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'legacy_person_id']);
        $input['role'] ??= 'editor';
        $input['is_active'] = true;
        $user = $this->accounts->create($request->user(), $input);

        return response()->json(['data' => $this->userData($user)], 201);
    }

    public function updateUser(Request $request, int $user): JsonResponse
    {
        $updated = $this->accounts->update($request->user(), $user,
            $request->only(['name', 'email', 'role', 'is_active', 'legacy_person_id', 'expected_version']));

        return response()->json(['data' => $this->userData($updated)]);
    }

    public function authors(Request $request): JsonResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json(['data' => $this->authors->listing($input['q'] ?? null)]);
    }

    public function author(int $author): JsonResponse
    {
        return response()->json(['data' => $this->authorData(EditorialAuthor::findOrFail($author))]);
    }

    public function createAuthor(Request $request): JsonResponse
    {
        $author = $this->authors->create($request->user(), $request->only(['person_id', 'signature', 'slug', 'description']));

        return response()->json(['data' => $this->authorData($author)], 201);
    }

    public function updateAuthor(Request $request, int $author): JsonResponse
    {
        $updated = $this->authors->update($request->user(), $author,
            $request->only(['signature', 'slug', 'description', 'photo_media_id', 'expected_version']));

        return response()->json(['data' => $this->authorData($updated)]);
    }

    public function authorActive(Request $request, int $author, string $action): JsonResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        if ($action === 'activate') {
            $this->authors->activate($request->user(), $author, (int) $data['expected_version']);
        } else {
            $this->authors->deactivate($request->user(), $author, (int) $data['expected_version']);
        }

        return $this->author($author);
    }

    public function activateAuthor(Request $request, int $author): JsonResponse
    {
        return $this->authorActive($request, $author, 'activate');
    }

    public function deactivateAuthor(Request $request, int $author): JsonResponse
    {
        return $this->authorActive($request, $author, 'deactivate');
    }

    public function media(): JsonResponse
    {
        return response()->json(['data' => $this->media->listing()]);
    }

    public function medium(string $media): JsonResponse
    {
        return response()->json(['data' => $this->mediaData(EditorialMedia::findOrFail($media))]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file'], 'alt_text' => ['required', 'string', 'max:255']]);
        $media = $this->media->upload($request->user(), $data['file'], $data['alt_text']);

        return response()->json(['data' => $this->mediaData($media->refresh())], 201);
    }

    public function updateMedia(Request $request, string $media): JsonResponse
    {
        $this->media->updateAltText($request->user(), $media, $request->only(['alt_text', 'expected_version']));

        return $this->medium($media);
    }

    public function mediaActive(Request $request, string $media, string $action): JsonResponse
    {
        if ($action === 'activate') {
            $this->media->activate($request->user(), $media);
        } else {
            $this->media->deactivate($request->user(), $media);
        }

        return $this->medium($media);
    }

    public function activateMedia(Request $request, string $media): JsonResponse
    {
        return $this->mediaActive($request, $media, 'activate');
    }

    public function deactivateMedia(Request $request, string $media): JsonResponse
    {
        return $this->mediaActive($request, $media, 'deactivate');
    }

    public function posts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:PP,PO,PE,PR'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);
        $page = $this->posts->listing($data['q'] ?? null, $data['status'] ?? null);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function post(int $post): JsonResponse
    {
        $snapshot = $this->posts->preview($post);
        $row = $snapshot['row'];

        return response()->json(['data' => [
            'id' => (int) $row->ID_POST, 'title' => $row->TITULO_POST, 'slug' => $row->LINK_POST,
            'status' => $row->STATUS_POST, 'html' => $snapshot['safe_html'],
            'snippet' => $row->SNIPPET_POST, 'description' => $row->DESCRICAO_POST,
            'keywords' => $row->KEYWORDS_POST, 'cover_url' => $row->URL_IMAGEM_POST,
            'author_id' => $snapshot['author_id'], 'media_id' => $snapshot['media_id'],
            'main_category_id' => $row->ID_CATEGORIA, 'category_ids' => $snapshot['category_ids'],
            'fingerprint' => $snapshot['fingerprint'],
        ]]);
    }

    public function createPost(PostContentRequest $request): JsonResponse
    {
        $id = $this->posts->create($request->user(), $request->validated());

        return $this->post($id)->setStatusCode(201);
    }

    public function updatePost(PostContentRequest $request, int $post): JsonResponse
    {
        try {
            $this->posts->update($request->user(), $post, $request->validated());
        } catch (PostConflict $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return $this->post($post);
    }

    public function transitionPost(Request $request, int $post, string $action): JsonResponse
    {
        $data = $request->validate([
            'expected_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'confirm_delete' => [$action === 'delete' ? 'accepted' : 'sometimes'],
        ]);
        try {
            $this->posts->transition($request->user(), $post, $action, $data['expected_fingerprint']);
        } catch (PostConflict $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return $this->post($post);
    }

    public function publishPost(Request $request, int $post): JsonResponse
    {
        return $this->transitionPost($request, $post, 'publish');
    }

    public function hidePost(Request $request, int $post): JsonResponse
    {
        return $this->transitionPost($request, $post, 'hide');
    }

    public function deletePost(Request $request, int $post): JsonResponse
    {
        return $this->transitionPost($request, $post, 'delete');
    }

    public function order(): JsonResponse
    {
        try {
            $listing = $this->legacyPosts->publishedWithRevision();
        } catch (EditorialUnavailable) {
            return response()->json(['message' => 'Ordenação temporariamente indisponível.'], 503);
        } catch (Throwable $error) {
            $correlationId = (string) Str::uuid();
            Log::error('Falha ao consultar a ordem pela API.', [
                'correlation_id' => $correlationId, 'exception_type' => $error::class,
            ]);

            return response()->json([
                'message' => 'Ordenação temporariamente indisponível.', 'correlation_id' => $correlationId,
            ], 503)->header('X-Correlation-ID', $correlationId);
        }

        $posts = array_map(fn (array $post) => [
            'id' => $post['id'], 'title' => $post['title'],
            'slug' => $post['slug'], 'sort_order' => $post['sort_order'],
        ], $listing['posts']);

        return response()->json(['data' => $posts, 'meta' => ['revision' => $listing['revision']]]);
    }

    public function saveOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $correlationId = (string) Str::uuid();
        try {
            $result = $this->ordering->save(array_map('intval', $data['ids']), (int) $data['expected_revision'],
                (int) $request->user()->id, $data['idempotency_key'], $correlationId);
        } catch (OrderConflict) {
            return response()->json(['message' => 'A ordem mudou. Recarregue a lista antes de salvar novamente.', 'current_revision' => $this->ordering->revision()], 409);
        } catch (EditorialUnavailable) {
            return response()->json(['message' => 'Ordenação temporariamente indisponível.'], 503);
        } catch (Throwable $error) {
            Log::error('Falha ao salvar a ordem pela API.', [
                'correlation_id' => $correlationId, 'exception_type' => $error::class,
            ]);

            return response()->json([
                'message' => 'Ordenação temporariamente indisponível.', 'correlation_id' => $correlationId,
            ], 503)->header('X-Correlation-ID', $correlationId);
        }

        return response()->json(['data' => $result]);
    }

    private function userData(AdminUser $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'is_active', 'legacy_person_id', 'auth_version']);
    }

    private function authorData(EditorialAuthor $author): array
    {
        return $author->only(['id', 'legacy_author_id', 'person_id', 'signature', 'slug', 'description', 'photo_media_id', 'is_active', 'version']);
    }

    private function mediaData(EditorialMedia $media): array
    {
        return $media->only(['id', 'original_name', 'public_mime', 'width', 'height', 'alt_text', 'is_active', 'version']);
    }
}
