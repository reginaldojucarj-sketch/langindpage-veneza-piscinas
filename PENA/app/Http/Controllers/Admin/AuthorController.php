<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialAuthor;
use App\Services\AuthorLibrary;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthorController extends Controller
{
    public function __construct(
        private readonly AuthorLibrary $authors,
        private readonly MediaLibrary $media,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return view('admin.authors', [
            'authors' => $this->authors->listing($filters['q'] ?? null),
            'search' => $filters['q'] ?? '',
        ]);
    }

    public function create(): View
    {
        return view('admin.author-form', [
            'author' => null,
            'people' => $this->authors->people(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $author = $this->authors->create($request->user(), $request->all());

        return redirect()->route('admin.authors.edit', $author)->with('status', 'Autor cadastrado.');
    }

    public function edit(int $author): View
    {
        $record = EditorialAuthor::query()->findOrFail($author);

        return view('admin.author-form', [
            'author' => $record,
            'people' => $this->authors->people(),
            'media' => $this->media->authorPhotoOptions($record->photo_media_id),
        ]);
    }

    public function update(Request $request, int $author): RedirectResponse
    {
        $this->authors->update($request->user(), $author, $request->all());

        return redirect()->route('admin.authors.edit', $author)->with('status', 'Dados do autor atualizados.');
    }

    public function deactivate(Request $request, int $author): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        $this->authors->deactivate($request->user(), $author, (int) $data['expected_version']);

        return redirect()->route('admin.authors.index')->with('status', 'Autor desativado. A assinatura histórica dos artigos foi preservada.');
    }

    public function activate(Request $request, int $author): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        $this->authors->activate($request->user(), $author, (int) $data['expected_version']);

        return redirect()->route('admin.authors.edit', $author)->with('status', 'Autor reativado para novos vínculos.');
    }
}
