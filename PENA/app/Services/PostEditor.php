<?php

namespace App\Services;

use App\Exceptions\ContentUnavailable;
use App\Exceptions\PostConflict;
use App\Models\AdminUser;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Writes legacy posts only after an explicit engine/coordination gate. Never writes MyISAM. */
class PostEditor
{
    public const DESCRIPTION_MAX_BYTES = 65535;

    public function __construct(
        private readonly EditorialActor $actors,
        private readonly ContentAudit $audit,
        private readonly HtmlSanitizer $sanitizer,
        private readonly LegacyUtf8mb3 $charset,
    ) {}

    public function canWrite(): bool
    {
        if (! config('pena.editorial_writes_enabled')) {
            return false;
        }

        if (DB::getDriverName() === 'sqlite') {
            return app()->environment('testing');
        }

        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $required = [
            'POST_pena', 'CATEGORIA_POST_pena', 'CATEGORIA_pena', 'PESSOA_pena',
            'pena_editorial_state', 'pena_post_order', 'pena_admin_users', 'pena_authors', 'pena_media',
            'pena_post_author_assignments', 'pena_post_media_assignments', 'pena_content_audit',
        ];
        try {
            $engines = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())->whereIn('TABLE_NAME', $required)
                ->pluck('ENGINE', 'TABLE_NAME');

            return $engines->count() === count($required)
                && $engines->every(fn ($engine) => strtolower((string) $engine) === 'innodb');
        } catch (QueryException) {
            return false;
        }
    }

    public function listing(?string $search, ?string $status): LengthAwarePaginator
    {
        try {
            $query = DB::table('POST_pena')->select(['ID_POST', 'TITULO_POST', 'LINK_POST', 'STATUS_POST', 'DATA_ULTIMA_MODIFICACAO_POST'])
                ->orderByDesc('ID_POST');
            if ($search !== null && trim($search) !== '') {
                $pattern = '%'.strtr(trim($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(fn ($q) => $q->whereRaw("TITULO_POST LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LINK_POST LIKE ? ESCAPE '!'", [$pattern]));
            }
            if ($status !== null && $status !== '') {
                $query->where('STATUS_POST', $status);
            }

            return $query->paginate(25)->withQueryString()->through(fn ($row) => (array) $row);
        } catch (QueryException $error) {
            Log::error('Falha ao listar artigos editoriais.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        }
    }

    public function options(?array $current = null): array
    {
        $this->actors->ensureStorage();
        $authorQuery = DB::table('pena_authors')->where(fn ($query) => $query->where('is_active', true)->when($current['author_id'] ?? null, fn ($q, $id) => $q->orWhere('id', $id)));
        $media = DB::table('pena_media')->where('is_active', true)->orderByDesc('created_at')->limit(300)->get(['id', 'original_name']);
        if (($current['media_id'] ?? null) && ! $media->contains(fn ($item) => $item->id === $current['media_id'])) {
            $selected = DB::table('pena_media')->where('id', $current['media_id'])->first(['id', 'original_name']);
            if ($selected) {
                $media->push($selected);
            }
        }

        return [
            'authors' => $authorQuery->orderBy('signature')->get(['id', 'signature'])->all(),
            'categories' => DB::table('CATEGORIA_pena')->orderBy('NOME_CATEGORIA')->get(['ID_CATEGORIA', 'NOME_CATEGORIA'])->all(),
            'media' => $media->all(),
        ];
    }

    public function find(int $id): array
    {
        $snapshot = $this->snapshot($id);
        abort_unless($snapshot, 404);

        return $snapshot;
    }

    public function preview(int $id): array
    {
        $post = $this->find($id);
        $post['safe_html'] = $this->sanitizer->sanitize((string) $post['row']->CONTEUDO_POST);

        return $post;
    }

    public function create(AdminUser $actor, array $input): int
    {
        $this->assertWritable();
        $data = $this->prepare($input, null);

        return $this->write(function () use ($actor, $data): int {
            $state = $this->lockState();
            $fresh = $this->actors->fresh($actor);
            $personId = (int) ($fresh->legacy_person_id ?? 0);
            if ($personId < 1 || ! DB::table('PESSOA_pena')->where('ID_PESSOA', $personId)->exists()) {
                throw ValidationException::withMessages(['person' => 'Vincule explicitamente este usuário a uma pessoa legada antes de criar artigos.']);
            }
            [$author, $media] = $this->selections($data, null);
            $this->categories($data);
            $this->uniqueSlug($data['slug']);
            $now = now();
            $id = (int) DB::table('POST_pena')->insertGetId([
                'TITULO_POST' => $data['title'], 'LINK_POST' => $data['slug'],
                'CONTEUDO_POST' => $data['html'], 'SNIPPET_POST' => $data['snippet'],
                'DESCRICAO_POST' => $data['description'], 'KEYWORDS_POST' => $data['keywords'],
                'URL_IMAGEM_POST' => $this->coverUrl($data, $media),
                'STATUS_POST' => 'PO', 'DESTAQUE_POST' => 'N',
                'ID_PESSOA' => $personId, 'ID_AUTOR' => $author->legacy_author_id,
                'ID_CATEGORIA' => $data['main_category_id'], 'ID_IMAGENS' => null,
                'DATA_CRIACAO_POST' => $now, 'DATA_POSTAGEM_POST' => null,
                'DATA_ULTIMA_MODIFICACAO_POST' => $now,
            ], 'ID_POST');
            $this->syncLinks($id, $data, $author, $media, $fresh->id);
            $this->bumpState($state, false);
            $this->audit->record($fresh->id, 'post.created', 'post', $id, ['status' => 'PO']);

            return $id;
        });
    }

    public function update(AdminUser $actor, int $id, array $input): void
    {
        $this->assertWritable();
        $data = $this->prepare($input, $id);

        $this->write(function () use ($actor, $id, $data): void {
            $state = $this->lockState();
            $fresh = $this->actors->fresh($actor);
            $current = $this->snapshot($id, true);
            abort_unless($current, 404);
            $this->assertEditable($fresh, $current, $data['expected_fingerprint']);
            if (($current['row']->DATA_POSTAGEM_POST !== null || $current['row']->STATUS_POST === 'PP')
                && $data['slug'] !== (string) $current['row']->LINK_POST) {
                throw ValidationException::withMessages(['slug' => 'A URL de um artigo já publicado não pode ser alterada sem um histórico de redirecionamentos.']);
            }
            [$author, $media] = $this->selections($data, $current);
            $this->categories($data);
            $this->uniqueSlug($data['slug'], $id);
            DB::table('POST_pena')->where('ID_POST', $id)->update([
                'TITULO_POST' => $data['title'], 'LINK_POST' => $data['slug'],
                'CONTEUDO_POST' => $data['html'], 'SNIPPET_POST' => $data['snippet'],
                'DESCRICAO_POST' => $data['description'], 'KEYWORDS_POST' => $data['keywords'],
                'URL_IMAGEM_POST' => $this->coverUrl($data, $media),
                'ID_AUTOR' => $author->legacy_author_id, 'ID_CATEGORIA' => $data['main_category_id'],
                'DATA_ULTIMA_MODIFICACAO_POST' => now(),
            ]);
            $this->syncLinks($id, $data, $author, $media, $fresh->id, $current);
            $this->bumpState($state, false);
            $this->audit->record($fresh->id, 'post.updated', 'post', $id, ['fields' => ['title', 'slug', 'html', 'snippet', 'description', 'keywords', 'cover', 'author', 'categories']]);
        });
    }

    public function transition(AdminUser $actor, int $id, string $action, string $expectedFingerprint): void
    {
        $this->assertWritable();
        $this->write(function () use ($actor, $id, $action, $expectedFingerprint): void {
            $state = $this->lockState();
            $fresh = $this->actors->fresh($actor);
            abort_unless($fresh->isAdministrator(), 403);
            $current = $this->snapshot($id, true);
            abort_unless($current, 404);
            if (! hash_equals($current['fingerprint'], $expectedFingerprint)) {
                throw new PostConflict('O artigo mudou desde que você abriu a página.');
            }
            $from = (string) $current['row']->STATUS_POST;
            $target = match ($action) {
                'publish' => 'PP', 'hide' => 'PO', 'delete' => 'PE',
                default => throw new PostConflict('Transição editorial inválida.'),
            };
            if ($from === 'PE' || $from === 'PR' || $from === $target || ($action === 'publish' && $from !== 'PO') || ($action === 'hide' && $from !== 'PP')) {
                throw new PostConflict('Este estado editorial não permite a ação solicitada.');
            }
            if ($action === 'publish') {
                $slug = (string) $current['row']->LINK_POST;
                if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
                    throw ValidationException::withMessages(['slug' => 'Revise a URL amigável antes de publicar.']);
                }
                $title = trim((string) $current['row']->TITULO_POST);
                $this->charset->assertSupported(['title' => $title], ['title' => 71]);
                if (mb_strlen($title, 'UTF-8') < 2) {
                    throw ValidationException::withMessages(['title' => 'Informe um título com pelo menos 2 caracteres antes de publicar.']);
                }
                if ($current['author_id'] === null || ! DB::table('pena_authors')->where('id', $current['author_id'])->where('is_active', true)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['author_id' => 'Selecione um autor ativo antes de publicar.']);
                }
                $mainCategoryId = (int) $current['row']->ID_CATEGORIA;
                $categoryIds = $current['category_ids'];
                if ($mainCategoryId < 1 || ! in_array($mainCategoryId, $categoryIds, true)) {
                    throw ValidationException::withMessages(['main_category_id' => 'Selecione uma categoria principal vinculada ao artigo antes de publicar.']);
                }
                $existingCategoryIds = DB::table('CATEGORIA_pena')->whereIn('ID_CATEGORIA', $categoryIds)
                    ->lockForUpdate()->pluck('ID_CATEGORIA');
                if (count($categoryIds) !== count(array_unique($categoryIds)) || $existingCategoryIds->count() !== count($categoryIds)) {
                    throw ValidationException::withMessages(['category_ids' => 'Revise as categorias vinculadas ao artigo antes de publicar.']);
                }
                if (trim((string) $current['row']->CONTEUDO_POST) === '') {
                    throw ValidationException::withMessages(['html' => 'Informe o conteúdo antes de publicar.']);
                }
            }
            DB::table('POST_pena')->where('ID_POST', $id)->update([
                'STATUS_POST' => $target,
                'DATA_POSTAGEM_POST' => $action === 'publish' && ! $current['row']->DATA_POSTAGEM_POST ? now() : $current['row']->DATA_POSTAGEM_POST,
                'DATA_ULTIMA_MODIFICACAO_POST' => now(),
            ]);
            $this->bumpState($state, true);
            $this->audit->record($fresh->id, 'post.'.$action, 'post', $id, ['from' => $from, 'to' => $target]);
        });
    }

    private function prepare(array $input, ?int $id): array
    {
        $title = trim((string) $input['title']);
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '' && $id === null) {
            $slug = Str::slug($title);
        }
        if ($slug === '' || mb_strlen($slug) > 200 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            throw ValidationException::withMessages(['slug' => 'Informe uma URL amigável em minúsculas, sem espaços ou caracteres especiais.']);
        }
        $rawHtml = (string) $input['html'];
        $this->charset->assertSupported(['html' => $rawHtml]);
        $data = [
            'title' => $title, 'slug' => $slug,
            'html' => $this->sanitizer->sanitize($rawHtml),
            'snippet' => trim((string) ($input['snippet'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'keywords' => trim((string) ($input['keywords'] ?? '')),
            'cover_url' => trim((string) ($input['cover_url'] ?? '')),
            'author_id' => (int) $input['author_id'],
            'media_id' => ($input['media_id'] ?? '') ?: null,
            'main_category_id' => (int) $input['main_category_id'],
            'category_ids' => array_values(array_unique(array_map('intval', $input['category_ids']))),
            'expected_fingerprint' => (string) ($input['expected_fingerprint'] ?? ''),
        ];
        $this->charset->assertSupported(array_intersect_key($data, array_flip(['title', 'slug', 'html', 'snippet', 'description', 'keywords', 'cover_url'])), [
            'title' => 71, 'slug' => 200, 'snippet' => 156, 'keywords' => 200, 'cover_url' => 200,
        ]);
        if (strlen($data['description']) > self::DESCRIPTION_MAX_BYTES) {
            throw ValidationException::withMessages(['description' => 'A descrição excede o limite de 65.535 bytes do banco de dados.']);
        }
        if ($data['cover_url'] !== '' && ! $this->safeCoverUrl($data['cover_url'])) {
            throw ValidationException::withMessages(['cover_url' => 'Use uma URL HTTP(S) ou um caminho local iniciado por /.']);
        }

        return $data;
    }

    private function safeCoverUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url) || str_starts_with($url, '//')) {
            return false;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['scheme'])) {
            return in_array(strtolower($parts['scheme']), ['http', 'https'], true) && isset($parts['host']);
        }

        return str_starts_with($url, '/') && ! str_contains(rawurldecode($url), '..');
    }

    private function selections(array $data, ?array $current): array
    {
        $author = DB::table('pena_authors')->where('id', $data['author_id'])->lockForUpdate()->first();
        if (! $author || (! $author->is_active && $data['author_id'] !== ($current['author_id'] ?? null))) {
            throw ValidationException::withMessages(['author_id' => 'Selecione um autor ativo.']);
        }
        $media = null;
        if ($data['media_id'] !== null) {
            $media = DB::table('pena_media')->where('id', $data['media_id'])->lockForUpdate()->first();
            if (! $media || (! $media->is_active && $data['media_id'] !== ($current['media_id'] ?? null))) {
                throw ValidationException::withMessages(['media_id' => 'Selecione uma imagem ativa.']);
            }
        }

        return [$author, $media];
    }

    private function categories(array $data): void
    {
        if (! in_array($data['main_category_id'], $data['category_ids'], true)) {
            throw ValidationException::withMessages(['category_ids' => 'A categoria principal deve estar entre as categorias selecionadas.']);
        }
        $count = DB::table('CATEGORIA_pena')->whereIn('ID_CATEGORIA', $data['category_ids'])->count();
        if ($count !== count($data['category_ids'])) {
            throw ValidationException::withMessages(['category_ids' => 'Uma das categorias selecionadas não existe.']);
        }
    }

    private function uniqueSlug(string $slug, ?int $except = null): void
    {
        $query = DB::table('POST_pena')->whereRaw('LOWER(LINK_POST) = ?', [$slug]);
        if ($except !== null) {
            $query->where('ID_POST', '<>', $except);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['slug' => 'Esta URL amigável já está em uso.']);
        }
    }

    private function coverUrl(array $data, ?object $media): ?string
    {
        $url = $media ? route('media.public', ['media' => $media->id]) : $data['cover_url'];
        if (mb_strlen($url) > 200) {
            throw ValidationException::withMessages(['cover_url' => 'A URL final da capa excede 200 caracteres.']);
        }

        return $url === '' ? null : $url;
    }

    private function syncLinks(int $id, array $data, object $author, ?object $media, int $actorId, ?array $current = null): void
    {
        DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->delete();
        foreach ($data['category_ids'] as $categoryId) {
            DB::table('CATEGORIA_POST_pena')->insert(['ID_POST' => $id, 'ID_CATEGORIA' => $categoryId]);
        }
        if ((int) ($current['author_id'] ?? 0) !== (int) $author->id) {
            DB::table('pena_post_author_assignments')->updateOrInsert(['post_id' => $id], [
                'author_id' => $author->id, 'author_signature_snapshot' => $author->signature,
                'source' => 'admin', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ($media && ($current['media_id'] ?? null) !== $media->id) {
            DB::table('pena_post_media_assignments')->updateOrInsert(['post_id' => $id], [
                'media_id' => $media->id, 'alt_text_snapshot' => $media->alt_text,
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } elseif (! $media) {
            DB::table('pena_post_media_assignments')->where('post_id', $id)->delete();
        }
    }

    private function snapshot(int $id, bool $lock = false): ?array
    {
        $this->actors->ensureStorage();
        $query = DB::table('POST_pena')->where('ID_POST', $id);
        $row = $lock ? $query->lockForUpdate()->first() : $query->first();
        if (! $row) {
            return null;
        }
        $categoryIds = DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->orderBy('ID_CATEGORIA')->pluck('ID_CATEGORIA')->map(fn ($value) => (int) $value)->all();
        $authorId = DB::table('pena_post_author_assignments')->where('post_id', $id)->value('author_id');
        $mediaId = DB::table('pena_post_media_assignments')->where('post_id', $id)->value('media_id');
        $revision = Schema::hasTable('pena_editorial_state') ? (int) DB::table('pena_editorial_state')->where('id', 1)->value('revision') : 0;

        return [
            'row' => $row, 'category_ids' => $categoryIds,
            'author_id' => $authorId === null ? null : (int) $authorId, 'media_id' => $mediaId,
            'fingerprint' => hash('sha256', json_encode([(array) $row, $categoryIds, $authorId, $mediaId, $revision], JSON_THROW_ON_ERROR)),
        ];
    }

    private function assertEditable(AdminUser $actor, array $current, string $expected): void
    {
        if (! hash_equals($current['fingerprint'], $expected)) {
            throw new PostConflict('O artigo mudou desde que você abriu o formulário.');
        }
        $status = (string) $current['row']->STATUS_POST;
        if ($status === 'PE' || $status === 'PR' || ($status === 'PP' && ! $actor->isAdministrator())) {
            throw new PostConflict('Este artigo não pode ser editado com sua permissão ou estado atual.');
        }
    }

    private function lockState(): object
    {
        $state = DB::table('pena_editorial_state')->where('id', 1)->lockForUpdate()->first();
        if (! $state) {
            throw new ContentUnavailable;
        }

        return $state;
    }

    private function bumpState(object $state, bool $publicationChanged): void
    {
        if ($publicationChanged) {
            DB::table('pena_post_order')->delete();
        }
        $ids = DB::table('POST_pena')->where('STATUS_POST', 'PP')->pluck('ID_POST')->map(fn ($id) => (int) $id)->all();
        DB::table('pena_editorial_state')->where('id', 1)->update([
            'revision' => (int) $state->revision + 1,
            'published_digest' => EditorialOrdering::digest($ids), 'updated_at' => now(),
        ]);
    }

    private function assertWritable(): void
    {
        if (! $this->canWrite()) {
            throw new ContentUnavailable;
        }
        $this->actors->ensureStorage();
    }

    private function write(callable $operation): mixed
    {
        try {
            return DB::transaction($operation, 3);
        } catch (ValidationException|PostConflict $error) {
            throw $error;
        } catch (QueryException $error) {
            if (in_array((string) ($error->errorInfo[1] ?? ''), ['1062', '19'], true)) {
                throw ValidationException::withMessages(['slug' => 'Esta URL amigável já está em uso.']);
            }
            Log::error('Falha ao gravar artigo.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);

            throw new ContentUnavailable;
        } catch (HttpExceptionInterface|ContentUnavailable $error) {
            throw $error;
        } catch (Throwable $error) {
            Log::error('Falha inesperada ao gravar artigo.', ['exception_type' => $error::class]);

            throw new ContentUnavailable;
        }
    }
}
