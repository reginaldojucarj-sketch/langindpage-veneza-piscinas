<?php

namespace Tests\Support;

use App\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Synthetic legacy schema. Never a substitute for a restored production backup. */
abstract class LegacyDatabaseTestCase extends TestCase
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

    protected function admin(): AdminUser
    {
        return AdminUser::create(['name' => 'Teste', 'email' => 'teste@example.com', 'password' => 'senha-de-teste-123']);
    }

    protected function installOrderTable(): void
    {
        (require database_path('migrations/2026_10_05_000001_create_pena_post_order_table.php'))->up();
    }
}
