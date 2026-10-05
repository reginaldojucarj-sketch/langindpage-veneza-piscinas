<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\LegacyPostRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostOrderController extends Controller
{
    public function __construct(private readonly LegacyPostRepository $posts) {}

    public function index(): View
    {
        return view('admin.post-order', [
            'posts' => $this->posts->published(),
            'canSave' => Schema::hasTable('pena_post_order'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        if (! Schema::hasTable('pena_post_order')) {
            throw ValidationException::withMessages(['ids' => 'A tabela de ordenação ainda não foi instalada.']);
        }

        $submitted = array_map('intval', $data['ids']);
        $expected = DB::transaction(function () use ($submitted) {
            $published = DB::table('POST_pena')->where('STATUS_POST', 'PP')
                ->lockForUpdate()->pluck('ID_POST')->map(fn ($id) => (int) $id)->all();

            $sortedPublished = $published;
            $sortedSubmitted = $submitted;
            sort($sortedPublished, SORT_NUMERIC);
            sort($sortedSubmitted, SORT_NUMERIC);
            if ($sortedPublished !== $sortedSubmitted) {
                throw ValidationException::withMessages(['ids' => 'A lista mudou. Recarregue a página antes de salvar.']);
            }

            $now = now();
            $rows = array_map(fn ($id, $index) => [
                'post_id' => $id,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $submitted, array_keys($submitted));
            DB::table('pena_post_order')->upsert($rows, ['post_id'], ['sort_order', 'updated_at']);

            return count($published);
        });

        return redirect()->route('admin.posts.order.index')
            ->with('status', "Ordem de {$expected} artigos publicada.");
    }
}
