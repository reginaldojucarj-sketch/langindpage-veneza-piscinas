<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyPostRepository
{
    /**
     * Read-only adapter to the original PENA tables. The schema has not yet
     * been verified against a secure database backup.
     */
    public function published(): array
    {
        $query = $this->query()->where('p.STATUS_POST', 'PP');
        if (Schema::hasTable('pena_post_order')) {
            $query->orderByRaw('ordering.sort_order IS NULL')->orderBy('ordering.sort_order');
        }

        $rows = $query->orderByRaw('COALESCE(p.DATA_POSTAGEM_POST, p.DATA_CRIACAO_POST) DESC')
            ->orderByDesc('p.ID_POST')->get();

        return $rows->map(fn ($row) => $this->normalize($row))->all();
    }

    public function findPublished(int $id): ?array
    {
        $row = $this->query()->where('p.STATUS_POST', 'PP')
            ->where('p.ID_POST', $id)->first();

        return $row ? $this->normalize($row) : null;
    }

    private function query()
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
                'p.ID_POST as id', 'p.TITULO_POST as title', 'p.CONTEUDO_POST as html',
                'p.DESCRICAO_POST as description', 'p.SNIPPET_POST as snippet',
                'p.STATUS_POST as status', 'p.DESTAQUE_POST as highlight',
                'p.DATA_POSTAGEM_POST as published_at', 'p.DATA_CRIACAO_POST as created_at',
                'p.DATA_ULTIMA_MODIFICACAO_POST as updated_at', 'p.LINK_POST as slug',
                'p.KEYWORDS_POST as keywords', 'p.URL_IMAGEM_POST as post_image',
                'a.ASSINATURA_AUTOR as author_signature', 'person.NOME_PESSOA as first_name',
                'person.SOBRENOME_PESSOA as last_name', 'category.NOME_CATEGORIA as category',
                'category_links.category_names as categories',
                'images.ENDERECO_IMAGENS as media_image', 'images.NOME_IMAGENS as image_name',
            ]);

        if (Schema::hasTable('pena_post_order')) {
            $query->leftJoin('pena_post_order as ordering', 'ordering.post_id', '=', 'p.ID_POST')
                ->addSelect('ordering.sort_order');
        } else {
            $query->selectRaw('NULL as sort_order');
        }

        return $query;
    }

    private function normalize(object $row): array
    {
        $author = trim((string) ($row->author_signature ?: trim(($row->first_name ?? '').' '.($row->last_name ?? ''))));

        return [
            'id' => (int) $row->id,
            'title' => $row->title,
            'html' => $row->html,
            'description' => $row->description,
            'snippet' => $row->snippet,
            'status' => $row->status,
            'highlight' => $row->highlight,
            'published_at' => $row->published_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'slug' => $row->slug,
            'keywords' => $row->keywords,
            'author' => $author ?: null,
            'category' => $row->category,
            'categories' => $row->categories ?: $row->category,
            'image' => $row->post_image ?: $row->media_image,
            'image_name' => $row->image_name,
            'sort_order' => $row->sort_order === null ? null : (int) $row->sort_order,
        ];
    }
}
