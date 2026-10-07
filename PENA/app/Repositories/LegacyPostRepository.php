<?php

namespace App\Repositories;

use App\Exceptions\PublishedListingChanged;
use App\Services\EditorialOrdering;
use App\Services\HtmlSanitizer;
use App\Support\LegacyPostData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyPostRepository
{
    public function __construct(
        private readonly EditorialOrdering $ordering,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /** Read-only adapter to the original PENA tables; never writes legacy rows. */
    public function published(): array
    {
        return $this->publishedWithRevision()['posts'];
    }

    /**
     * Return the ordered published articles together with the revision observed
     * in the same ordering-state read. Admin forms use this pair for optimistic
     * concurrency; they must not fetch the revision in a later query.
     *
     * @return array{posts:list<array<string, mixed>>,revision:int|null}
     */
    public function publishedWithRevision(): array
    {
        $rows = $this->query()->where('p.STATUS_POST', 'PP')
            ->orderByRaw('COALESCE(p.DATA_POSTAGEM_POST, p.DATA_CRIACAO_POST) DESC')
            ->orderByDesc('p.ID_POST')->get();
        $order = $this->ordering->currentOrderSnapshot($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $rank = $order['rank'];

        if ($rank !== null) {
            $rows = $rows->sortBy(fn ($row) => $rank[(int) $row->id])->values();
        }

        $posts = $rows->map(function ($row) use ($rank) {
            $row->sort_order = $rank === null ? null : $rank[(int) $row->id] + 1;

            return LegacyPostData::fromRow($row, $this->sanitizer);
        })->all();

        return ['posts' => $posts, 'revision' => $order['revision']];
    }

    /**
     * Paginated listing data intentionally excludes article HTML. The public
     * detail endpoint remains the only API response that sanitizes/returns it.
     *
     * @return array{data:list<array<string, mixed>>,meta:array{current_page:int,per_page:int,total:int,last_page:int}}
     */
    public function publishedPage(int $page, int $perPage, ?string $search = null): array
    {
        $startState = $this->listingState();
        $publishedIds = $startState['published_ids'];
        $rank = $startState['rank'];
        $total = count($publishedIds);
        $offset = ($page - 1) * $perPage;
        $snapshot = $startState['snapshot'];

        if ($search !== null && trim($search) !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $searchableColumns = [
                'p.TITULO_POST', 'p.DESCRICAO_POST', 'p.SNIPPET_POST', 'p.KEYWORDS_POST',
                'p.CONTEUDO_POST', 'a.ASSINATURA_AUTOR', 'person.NOME_PESSOA',
                'person.SOBRENOME_PESSOA', 'category.NOME_CATEGORIA', 'category_links.category_names',
            ];
            $matching = $this->query(false)->where('p.STATUS_POST', 'PP')
                ->whereIn('p.ID_POST', $publishedIds)->where(function ($query) use ($searchableColumns, $pattern) {
                    foreach ($searchableColumns as $column) {
                        $query->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                    }
                });

            if ($rank === null) {
                $matching->orderByRaw('COALESCE(p.DATA_POSTAGEM_POST, p.DATA_CRIACAO_POST) DESC')
                    ->orderByDesc('p.ID_POST');
            }
            $rows = $matching->get();
            if ($rank !== null) {
                $rows = $rows->sortBy(fn ($row) => $rank[(int) $row->id] ?? PHP_INT_MAX)->values();
            }
            $total = $rows->count();
            $matchingIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
            $snapshot = hash('sha256', $snapshot.':search:'.hash('sha256', trim($search)).':'.implode(',', $matchingIds));
            $rows = $rows->slice($offset, $perPage)->values();
        } elseif ($rank !== null) {
            asort($rank, SORT_NUMERIC);
            $orderedIds = array_map('intval', array_keys($rank));
            $pageIds = array_slice($orderedIds, $offset, $perPage);
            $rows = $pageIds === []
                ? collect()
                : $this->query(false)->where('p.STATUS_POST', 'PP')->whereIn('p.ID_POST', $pageIds)->get()
                    ->sortBy(fn ($row) => $rank[(int) $row->id])->values();
        } else {
            $rows = $this->query(false)->where('p.STATUS_POST', 'PP')
                ->whereIn('p.ID_POST', $publishedIds)
                ->orderByRaw('COALESCE(p.DATA_POSTAGEM_POST, p.DATA_CRIACAO_POST) DESC')
                ->orderByDesc('p.ID_POST')->offset($offset)->limit($perPage)->get();
        }

        $endState = $this->listingState();
        if (! hash_equals($startState['snapshot'], $endState['snapshot'])) {
            throw new PublishedListingChanged;
        }

        $data = $rows->map(function ($row) use ($rank) {
            $row->sort_order = $rank === null ? null : (($rank[(int) $row->id] ?? null) === null ? null : $rank[(int) $row->id] + 1);

            return LegacyPostData::summaryFromRow($row);
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'snapshot' => $snapshot,
            ],
        ];
    }

    /** @return array{published_ids:list<int>,rank:array<int,int>|null,snapshot:string} */
    private function listingState(): array
    {
        $publishedIds = DB::table('POST_pena')->where('STATUS_POST', 'PP')
            ->pluck('ID_POST')->map(fn ($id) => (int) $id)->all();
        $order = $this->ordering->currentOrderSnapshot($publishedIds);
        $rank = $order['rank'];

        if ($rank !== null) {
            $orderedRank = $rank;
            asort($orderedRank, SORT_NUMERIC);
            $orderedIds = array_map('intval', array_keys($orderedRank));
        } elseif ($publishedIds === []) {
            $orderedIds = [];
        } else {
            $orderedIds = DB::table('POST_pena')->where('STATUS_POST', 'PP')->whereIn('ID_POST', $publishedIds)
                ->orderByRaw('COALESCE(DATA_POSTAGEM_POST, DATA_CRIACAO_POST) DESC')
                ->orderByDesc('ID_POST')->pluck('ID_POST')->map(fn ($id) => (int) $id)->all();
        }

        $revision = $order['revision'] ?? 'legacy';
        $snapshot = hash('sha256', EditorialOrdering::digest($publishedIds).':'.$revision.':'.implode(',', $orderedIds));

        return ['published_ids' => $publishedIds, 'rank' => $rank, 'snapshot' => $snapshot];
    }

    public function findPublished(int $id): ?array
    {
        $row = $this->query()->where('p.STATUS_POST', 'PP')
            ->where('p.ID_POST', $id)->first();

        if ($row === null) {
            return null;
        }

        $publishedIds = DB::table('POST_pena')->where('STATUS_POST', 'PP')
            ->pluck('ID_POST')->map(fn ($postId) => (int) $postId)->all();
        $rank = $this->ordering->currentOrder($publishedIds);
        $row->sort_order = $rank === null || ! array_key_exists($id, $rank) ? null : $rank[$id] + 1;

        return LegacyPostData::fromRow($row, $this->sanitizer);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $record = DB::table('POST_pena')->select(['ID_POST', 'LINK_POST'])
            ->where('STATUS_POST', 'PP')->where('LINK_POST', $slug)->first();
        if ($record === null || ! hash_equals((string) $record->LINK_POST, $slug)) {
            return null;
        }

        $post = $this->findPublished((int) $record->ID_POST);

        return $post !== null && hash_equals((string) $post['slug'], $slug) ? $post : null;
    }

    private function query(bool $includeHtml = true)
    {
        $query = DB::table('POST_pena as p')
            ->leftJoin('AUTOR_pena as a', 'a.ID_AUTOR', '=', 'p.ID_AUTOR')
            ->leftJoin('PESSOA_pena as person', 'person.ID_PESSOA', '=', 'p.ID_PESSOA')
            ->leftJoin('IMAGENS_pena as images', 'images.ID_IMAGENS', '=', 'p.ID_IMAGENS')
            ->leftJoin('CATEGORIA_pena as category', 'category.ID_CATEGORIA', '=', 'p.ID_CATEGORIA')
            ->leftJoinSub(
                DB::table('CATEGORIA_POST_pena as cp')
                    ->leftJoin('CATEGORIA_pena as c', 'c.ID_CATEGORIA', '=', 'cp.ID_CATEGORIA')
                    ->select('cp.ID_POST')
                    ->selectRaw('GROUP_CONCAT(DISTINCT c.NOME_CATEGORIA) as category_names')
                    ->groupBy('cp.ID_POST'),
                'category_links', 'category_links.ID_POST', '=', 'p.ID_POST'
            )
            ->select([
                'p.ID_POST as id', 'p.TITULO_POST as title',
                'p.DESCRICAO_POST as description', 'p.SNIPPET_POST as snippet',
                'p.STATUS_POST as status', 'p.DESTAQUE_POST as highlight',
                'p.DATA_POSTAGEM_POST as published_at', 'p.DATA_CRIACAO_POST as created_at',
                'p.DATA_ULTIMA_MODIFICACAO_POST as updated_at', 'p.LINK_POST as slug',
                'p.KEYWORDS_POST as keywords', 'p.URL_IMAGEM_POST as post_image',
                'category.NOME_CATEGORIA as category',
                'category_links.category_names as categories',
                'images.ENDERECO_IMAGENS as media_image', 'images.NOME_IMAGENS as image_name',
            ]);

        if (Schema::hasTable('pena_post_author_assignments')) {
            $query->leftJoin('pena_post_author_assignments as author_assignment', 'author_assignment.post_id', '=', 'p.ID_POST')
                ->leftJoin('pena_authors as editorial_author', 'editorial_author.id', '=', 'author_assignment.author_id')
                ->addSelect(DB::raw('COALESCE(author_assignment.author_signature_snapshot, editorial_author.signature, a.ASSINATURA_AUTOR) as author_signature'));
        } else {
            $query->addSelect('a.ASSINATURA_AUTOR as author_signature');
        }

        if (Schema::hasTable('pena_post_media_assignments')) {
            $query->leftJoin('pena_post_media_assignments as media_assignment', 'media_assignment.post_id', '=', 'p.ID_POST')
                ->leftJoin('pena_media as editorial_media', 'editorial_media.id', '=', 'media_assignment.media_id')
                ->addSelect([
                    'editorial_media.id as managed_media_id',
                    'media_assignment.alt_text_snapshot as managed_media_alt',
                ]);
        }

        $query->selectRaw('NULL as sort_order');

        if ($includeHtml) {
            $query->addSelect('p.CONTEUDO_POST as html');
        }

        return $query;
    }
}
