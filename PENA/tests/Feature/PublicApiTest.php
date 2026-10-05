<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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

    public function test_empty_catalog_returns_an_empty_array(): void
    {
        DB::table('POST_pena')->where('ID_POST', 1)->update(['STATUS_POST' => 'PO']);
        $this->getJson('/api/public/posts')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_relations_do_not_duplicate_posts_and_preserve_accents(): void
    {
        DB::table('AUTOR_pena')->insert(['ID_AUTOR' => 1, 'ASSINATURA_AUTOR' => 'Equipe técnica']);
        DB::table('IMAGENS_pena')->insert(['ID_IMAGENS' => 1, 'ENDERECO_IMAGENS' => 'uploads/água.jpg', 'NOME_IMAGENS' => 'Água']);
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
            ->assertJsonPath('data.0.author', 'Equipe técnica')->assertJsonPath('data.0.image', 'uploads/água.jpg');
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
        DB::table('pena_post_order')->insert(['post_id' => 1, 'sort_order' => 1]);
        $this->assertSame([1, 4, 3], array_column($this->getJson('/api/public/posts')->json('data'), 'id'));
    }

    public function test_missing_schema_returns_503_without_sql_or_connection_details(): void
    {
        Schema::drop('POST_pena');
        foreach (['/api/public/posts', '/api/public/posts/1'] as $route) {
            $this->getJson($route)->assertStatus(503)
                ->assertExactJson(['message' => 'Artigos temporariamente indisponíveis.'])
                ->assertDontSee('SQLSTATE')->assertDontSee('POST_pena');
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
