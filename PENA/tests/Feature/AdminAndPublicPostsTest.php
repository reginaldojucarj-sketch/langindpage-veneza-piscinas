<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAndPublicPostsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('POST_pena', function (Blueprint $table) {
            $table->integer('ID_POST')->primary();
            $table->string('TITULO_POST');
            $table->text('CONTEUDO_POST')->nullable();
            $table->text('DESCRICAO_POST')->nullable();
            $table->text('SNIPPET_POST')->nullable();
            $table->string('STATUS_POST');
            $table->string('DESTAQUE_POST')->nullable();
            $table->dateTime('DATA_POSTAGEM_POST')->nullable();
            $table->dateTime('DATA_CRIACAO_POST')->nullable();
            $table->dateTime('DATA_ULTIMA_MODIFICACAO_POST')->nullable();
            $table->string('LINK_POST')->nullable();
            $table->string('KEYWORDS_POST')->nullable();
            $table->string('URL_IMAGEM_POST')->nullable();
            $table->integer('ID_AUTOR')->nullable();
            $table->integer('ID_PESSOA')->nullable();
            $table->integer('ID_IMAGENS')->nullable();
            $table->integer('ID_CATEGORIA')->nullable();
        });
        Schema::create('AUTOR_pena', function (Blueprint $table) {
            $table->integer('ID_AUTOR')->primary();
            $table->string('ASSINATURA_AUTOR')->nullable();
        });
        Schema::create('PESSOA_pena', function (Blueprint $table) {
            $table->integer('ID_PESSOA')->primary();
            $table->string('NOME_PESSOA')->nullable();
            $table->string('SOBRENOME_PESSOA')->nullable();
        });
        Schema::create('IMAGENS_pena', function (Blueprint $table) {
            $table->integer('ID_IMAGENS')->primary();
            $table->string('ENDERECO_IMAGENS')->nullable();
            $table->string('NOME_IMAGENS')->nullable();
        });
        Schema::create('CATEGORIA_pena', function (Blueprint $table) {
            $table->integer('ID_CATEGORIA')->primary();
            $table->string('NOME_CATEGORIA')->nullable();
        });
        Schema::create('CATEGORIA_POST_pena', function (Blueprint $table) {
            $table->integer('ID_POST');
            $table->integer('ID_CATEGORIA');
        });
        Schema::create('pena_admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('POST_pena')->insert([
            ['ID_POST' => 1, 'TITULO_POST' => 'Publicado', 'STATUS_POST' => 'PP'],
            ['ID_POST' => 2, 'TITULO_POST' => 'Rascunho', 'STATUS_POST' => 'PO'],
        ]);
    }

    public function test_admin_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Acessar o painel');
    }

    public function test_public_api_excludes_unpublished_articles(): void
    {
        $this->getJson('/api/public/posts')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1);
        $this->getJson('/api/public/posts/1')->assertOk()->assertJsonPath('data.title', 'Publicado');
        $this->getJson('/api/public/posts/2')->assertNotFound();
    }

    public function test_admin_can_login_and_logout_with_a_local_test_account(): void
    {
        AdminUser::create(['name' => 'Teste', 'email' => 'teste@example.com', 'password' => 'senha-de-teste-123']);

        $this->post('/admin/login', ['email' => 'teste@example.com', 'password' => 'senha-errada'])
            ->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'teste@example.com', 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Painel administrativo');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_public_api_cors_uses_an_exact_origin_allowlist(): void
    {
        config()->set('cors.allowed_origins', ['https://site.example.com']);

        $this->withHeader('Origin', 'https://site.example.com')->getJson('/api/public/posts')
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example.com');
        $this->withHeader('Origin', 'https://other.example.com')->getJson('/api/public/posts')
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example.com');
    }

    public function test_admin_creation_refuses_to_run_without_a_backup(): void
    {
        $this->artisan('pena:create-admin')->assertExitCode(1);
        $this->assertDatabaseCount('pena_admin_users', 0);
    }

    public function test_login_explains_when_database_is_not_configured(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', '');

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'irrelevante'])
            ->assertSessionHasErrors('email');
    }
}
