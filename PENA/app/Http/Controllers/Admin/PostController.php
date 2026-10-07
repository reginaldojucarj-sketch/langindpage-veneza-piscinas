<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PostConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostContentRequest;
use App\Services\PostEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private readonly PostEditor $posts) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:PP,PO,PE,PR'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        return view('admin.posts', [
            'posts' => $this->posts->listing($data['q'] ?? null, $data['status'] ?? null),
            'search' => $data['q'] ?? '', 'status' => $data['status'] ?? '',
            'canWrite' => $this->posts->canWrite(),
        ]);
    }

    public function create(): View
    {
        return view('admin.post-form', [
            'post' => null, 'options' => $this->posts->options(),
            'canWrite' => $this->posts->canWrite(),
        ]);
    }

    public function store(PostContentRequest $request): RedirectResponse
    {
        $id = $this->posts->create($request->user(), $request->validated());

        return redirect()->route('admin.posts.edit', $id)->with('status', 'Artigo oculto criado. Publique-o separadamente após revisar a prévia.');
    }

    public function edit(int $post): View
    {
        $current = $this->posts->find($post);

        return view('admin.post-form', [
            'post' => $current, 'options' => $this->posts->options($current),
            'canWrite' => $this->posts->canWrite(),
        ]);
    }

    public function update(PostContentRequest $request, int $post): RedirectResponse|Response|JsonResponse
    {
        try {
            $this->posts->update($request->user(), $post, $request->validated());
        } catch (PostConflict $error) {
            return $this->conflict($request, $post, $error);
        }

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Artigo salvo.');
    }

    public function preview(int $post): Response
    {
        return response()->view('admin.post-preview', ['post' => $this->posts->preview($post)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function publish(Request $request, int $post): RedirectResponse|Response|JsonResponse
    {
        return $this->transition($request, $post, 'publish', 'Artigo publicado.');
    }

    public function hide(Request $request, int $post): RedirectResponse|Response|JsonResponse
    {
        return $this->transition($request, $post, 'hide', 'Artigo ocultado.');
    }

    public function delete(Request $request, int $post): RedirectResponse|Response|JsonResponse
    {
        return $this->transition($request, $post, 'delete', 'Artigo excluído logicamente.');
    }

    private function transition(Request $request, int $post, string $action, string $message): RedirectResponse|Response|JsonResponse
    {
        $data = $request->validate(['expected_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        if ($action === 'delete') {
            $request->validate(['confirm_delete' => ['accepted']]);
        }
        try {
            $this->posts->transition($request->user(), $post, $action, $data['expected_fingerprint']);
        } catch (PostConflict $error) {
            return $this->conflict($request, $post, $error);
        }

        return redirect()->route('admin.posts.index')->with('status', $message);
    }

    private function conflict(Request $request, int $post, PostConflict $error): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return response()->view('admin.post-conflict', [
            'message' => $error->getMessage(), 'postId' => $post,
            'draft' => $request->only(['title', 'slug', 'snippet', 'description', 'keywords', 'html']),
        ], 409);
    }
}
