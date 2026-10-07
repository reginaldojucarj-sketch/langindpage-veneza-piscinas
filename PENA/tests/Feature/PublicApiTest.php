<?php

namespace Tests\Feature;

use App\Services\EditorialOrdering;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyDatabaseTestCase;

class PublicApiTest extends LegacyDatabaseTestCase
{
    public function test_all_non_public_states_and_unknown_ids_are_unavailable(): void
    {
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Excluído', 'STATUS_POST' => 'PE']);
        foreach ([2, 3, 999, 'invalid', '-1'] as $id) {
            $this->getJson('/api/public/posts/'.$id)->assertNotFound();
        }
        $this->getJson('/api/public/posts')->assertJsonCount(1, 'data')->assertDontSee('Rascunho')->assertDontSee('Excluído');
    }

    public function test_slug_lookup_is_exact_and_only_returns_published_articles(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update(['LINK_POST' => 'agua-de-piscina']);
        DB::table('POST_pena')->where('ID_POST', 2)->update(['LINK_POST' => 'artigo-oculto']);
        DB::table('POST_pena')->insert([
            'ID_POST' => 3, 'TITULO_POST' => 'Excluído', 'LINK_POST' => 'artigo-excluido', 'STATUS_POST' => 'PE',
        ]);

        $this->getJson('/api/public/posts/slug/agua-de-piscina')->assertOk()
            ->assertJsonPath('data.id', 1)->assertJsonPath('data.slug', 'agua-de-piscina')
            ->assertHeader('Cache-Control', 'no-store, private');
        foreach (['artigo-oculto', 'artigo-excluido', 'nao-existe', 'AGUA-DE-PISCINA', 'agua%2Fde-piscina'] as $slug) {
            $this->getJson('/api/public/posts/slug/'.$slug)->assertNotFound();
        }
        $this->getJson('/api/public/posts/slug/'.str_repeat('a', 201))->assertNotFound();
    }

    public function test_empty_catalog_returns_an_empty_array(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update(['STATUS_POST' => 'PO']);
        $response = $this->getJson('/api/public/posts')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('meta.snapshot'));
    }

    public function test_listing_is_paginated_and_omits_html_while_detail_keeps_sanitized_html(): void
    {
        DB::table('POST_pena')->insert([
            ['ID_POST' => 3, 'TITULO_POST' => 'Artigo 3', 'CONTEUDO_POST' => '<p>Corpo 3</p>', 'STATUS_POST' => 'PP'],
            ['ID_POST' => 4, 'TITULO_POST' => 'Artigo 4', 'CONTEUDO_POST' => '<p>Corpo 4</p>', 'STATUS_POST' => 'PP'],
        ]);
        DB::table('POST_pena')->where('ID_POST', 1)->update(['CONTEUDO_POST' => '<p>Corpo completo</p>']);

        $firstPage = $this->getJson('/api/public/posts?per_page=2&page=1')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firstPage->json('meta.snapshot'));
        $this->assertArrayNotHasKey('html', $firstPage->json('data.0'));

        $this->getJson('/api/public/posts?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.snapshot', $firstPage->json('meta.snapshot'));
        $this->getJson('/api/public/posts/1')->assertOk()
            ->assertJsonPath('data.html', '<p>Corpo completo</p>');
    }

    public function test_listing_rejects_invalid_pagination_parameters(): void
    {
        $this->getJson('/api/public/posts?per_page=101')->assertUnprocessable();
        $this->getJson('/api/public/posts?page=0')->assertUnprocessable();
    }

    public function test_date_order_change_produces_a_different_page_snapshot(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update(['DATA_CRIACAO_POST' => '2026-01-01 00:00:00']);
        DB::table('POST_pena')->insert([
            'ID_POST' => 3, 'TITULO_POST' => 'Artigo mais recente', 'STATUS_POST' => 'PP',
            'DATA_CRIACAO_POST' => '2026-01-02 00:00:00',
        ]);

        $firstPage = $this->getJson('/api/public/posts?per_page=1&page=1')->assertOk();
        $this->assertSame(3, $firstPage->json('data.0.id'));

        DB::table('POST_pena')->where('ID_POST', 1)->update(['DATA_POSTAGEM_POST' => '2026-01-03 00:00:00']);
        $secondPage = $this->getJson('/api/public/posts?per_page=1&page=2')->assertOk();

        $this->assertNotSame($firstPage->json('meta.snapshot'), $secondPage->json('meta.snapshot'));
    }

    public function test_search_match_set_change_produces_a_different_page_snapshot(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update([
            'CONTEUDO_POST' => 'termo buscado', 'DATA_CRIACAO_POST' => '2026-01-01 00:00:00',
        ]);
        DB::table('POST_pena')->insert([
            ['ID_POST' => 3, 'TITULO_POST' => 'Segundo resultado', 'CONTEUDO_POST' => 'termo buscado', 'STATUS_POST' => 'PP', 'DATA_CRIACAO_POST' => '2026-01-02 00:00:00'],
            ['ID_POST' => 4, 'TITULO_POST' => 'Resultado que mudou', 'CONTEUDO_POST' => 'ainda sem o termo', 'STATUS_POST' => 'PP', 'DATA_CRIACAO_POST' => '2026-01-03 00:00:00'],
        ]);

        $firstPage = $this->getJson('/api/public/posts?q=buscado&per_page=1&page=1')->assertOk();
        $this->assertSame(2, $firstPage->json('meta.total'));

        DB::table('POST_pena')->where('ID_POST', 4)->update(['CONTEUDO_POST' => 'agora tem o termo buscado']);
        $secondPage = $this->getJson('/api/public/posts?q=buscado&per_page=1&page=2')->assertOk();

        $this->assertNotSame($firstPage->json('meta.snapshot'), $secondPage->json('meta.snapshot'));
    }

    public function test_listing_aborts_if_an_article_is_unpublished_between_reads(): void
    {
        $this->installOrderTable();
        DB::table('pena_post_order')->insert(['post_id' => 1, 'sort_order' => 1]);
        DB::table('pena_editorial_state')->where('id', 1)->update([
            'published_digest' => EditorialOrdering::digest([1]),
        ]);

        $changed = false;
        DB::listen(function (QueryExecuted $query) use (&$changed): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if (! $changed && str_contains($sql, 'select id_post from post_pena where status_post = ?')) {
                $changed = true;
                DB::table('POST_pena')->where('ID_POST', 1)->update(['STATUS_POST' => 'PO']);
            }
        });

        $response = $this->getJson('/api/public/posts')->assertStatus(409)
            ->assertJsonPath('message', 'A lista de artigos mudou durante a consulta. Recarregue e tente novamente.');

        $this->assertTrue($changed, 'The test must change legacy publication state between listing reads.');
        $this->assertArrayNotHasKey('data', $response->json());
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => 1, 'STATUS_POST' => 'PO']);
    }

    public function test_full_text_search_can_match_article_body_without_returning_that_body(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update([
            'DESCRICAO_POST' => 'Resumo curto',
            'CONTEUDO_POST' => '<p>Termo exclusivo dentro do corpo editorial.</p>',
        ]);

        $response = $this->getJson('/api/public/posts?q=exclusivo')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1)
            ->assertJsonPath('data.0.description', 'Resumo curto');

        $this->assertArrayNotHasKey('html', $response->json('data.0'));
        $this->assertSame('<p>Termo exclusivo dentro do corpo editorial.</p>', $this->getJson('/api/public/posts/1')->json('data.html'));
    }

    public function test_search_treats_like_wildcards_as_literal_text(): void
    {
        DB::table('POST_pena')->insert([
            ['ID_POST' => 3, 'TITULO_POST' => 'Desconto 50%', 'STATUS_POST' => 'PP'],
            ['ID_POST' => 4, 'TITULO_POST' => 'linha_azul', 'STATUS_POST' => 'PP'],
        ]);

        $percent = $this->getJson('/api/public/posts?q=%25')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(3, $percent->json('data.0.id'));
        $underscore = $this->getJson('/api/public/posts?q=_')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(4, $underscore->json('data.0.id'));
        $this->getJson('/api/public/posts?q=!')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_relations_do_not_duplicate_posts_and_preserve_accents(): void
    {
        DB::table('AUTOR_pena')->insert([
            'ID_AUTOR' => 1, 'ID_IMAGENS' => null, 'ASSINATURA_AUTOR' => 'Equipe técnica',
            'DESCRICAO_AUTOR' => null, 'STATUS_AUTOR' => 0, 'DATA_CRIACAO_AUTOR' => '2026-01-01 00:00:00',
            'URL_IMAGEM_AUTOR' => null, 'LINK_AUTOR' => 'equipe-tecnica', 'ID_PESSOA' => 1,
        ]);
        DB::table('IMAGENS_pena')->insert([
            'ID_IMAGENS' => 1, 'ENDERECO_IMAGENS' => 'uploads/água.jpg', 'STATUS_IMAGENS' => 0, 'NOME_IMAGENS' => 'Água',
        ]);
        DB::table('CATEGORIA_pena')->insert([
            ['ID_CATEGORIA' => 1, 'NOME_CATEGORIA' => 'Água'],
            ['ID_CATEGORIA' => 2, 'NOME_CATEGORIA' => 'Filtros'],
        ]);
        DB::table('CATEGORIA_POST_pena')->insert([
            ['ID_POST' => 1, 'ID_CATEGORIA' => 1], ['ID_POST' => 1, 'ID_CATEGORIA' => 2],
            ['ID_POST' => 1, 'ID_CATEGORIA' => 2],
        ]);
        DB::table('POST_pena')->where('ID_POST', 1)->update(['ID_AUTOR' => 1, 'ID_IMAGENS' => 1, 'ID_CATEGORIA' => 1]);
        $this->getJson('/api/public/posts')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author', 'Autoria sintética')->assertJsonPath('data.0.image', 'uploads/água.jpg');
        $categories = explode(',', $this->getJson('/api/public/posts/1')->json('data.categories'));
        $this->assertEqualsCanonicalizing(['Água', 'Filtros'], $categories);
    }

    public function test_manual_order_precedes_dates_and_unsorted_posts_have_stable_ties(): void
    {
        $this->installOrderTable();
        DB::table('POST_pena')->insert([
            ['ID_POST' => 3, 'TITULO_POST' => 'Novo', 'STATUS_POST' => 'PP', 'DATA_CRIACAO_POST' => '2026-01-01'],
            ['ID_POST' => 4, 'TITULO_POST' => 'Mesmo dia', 'STATUS_POST' => 'PP', 'DATA_CRIACAO_POST' => '2026-01-01'],
        ]);
        DB::table('pena_post_order')->insert([
            ['post_id' => 1, 'sort_order' => 1],
            ['post_id' => 4, 'sort_order' => 2],
            ['post_id' => 3, 'sort_order' => 3],
        ]);
        DB::table('pena_editorial_state')->where('id', 1)->update([
            'published_digest' => EditorialOrdering::digest([1, 3, 4]),
        ]);
        $first = $this->getJson('/api/public/posts?per_page=2')->assertOk();
        $second = $this->getJson('/api/public/posts?per_page=2&page=2')->assertOk();
        $this->assertSame([1, 4], array_column($first->json('data'), 'id'));
        $this->assertSame([3], array_column($second->json('data'), 'id'));
        $this->assertSame($first->json('meta.snapshot'), $second->json('meta.snapshot'));
    }

    public function test_missing_schema_returns_503_without_sql_or_connection_details(): void
    {
        Schema::drop('POST_pena');
        Log::shouldReceive('error')->twice()->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context);

            return array_keys($context) === ['correlation_id', 'exception_type', 'sqlstate']
                && ! array_key_exists('exception', $context)
                && ! str_contains((string) $encoded, 'POST_pena');
        });
        foreach (['/api/public/posts', '/api/public/posts/1'] as $route) {
            $response = $this->getJson($route)->assertStatus(503)
                ->assertExactJson(['message' => 'Artigos temporariamente indisponíveis.'])
                ->assertDontSee('SQLSTATE')->assertDontSee('POST_pena');
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Correlation-ID'));
        }
    }

    public function test_unconfigured_database_returns_503_without_connecting(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', '');
        $this->getJson('/api/public/posts')->assertStatus(503)
            ->assertExactJson(['message' => 'Base de dados não configurada.']);
    }

    public function test_public_routes_do_not_allow_writes(): void
    {
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->$method('/api/public/posts/1', ['title' => 'Alterado'])->assertStatus(405);
        }
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => 1, 'TITULO_POST' => 'Publicado']);
    }

    public function test_cors_preflight_allows_only_configured_origins_without_credentials(): void
    {
        config()->set('cors.allowed_origins', ['https://site.example.com', 'https://preview.example.com']);
        $this->withHeaders(['Origin' => 'https://site.example.com', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/public/posts')->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example.com')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->withHeader('Origin', 'https://untrusted.example.com')->getJson('/api/public/posts')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->withHeader('Origin', 'https://site.example.com')->get('/admin/login')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
