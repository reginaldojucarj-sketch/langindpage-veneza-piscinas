<?php

namespace Tests\Support;

use App\Models\AdminUser;
use Illuminate\Contracts\Auth\Authenticatable;
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
            $table->unsignedInteger('ID_IMAGENS')->nullable();
            $table->string('ASSINATURA_AUTOR', 50);
            $table->text('DESCRICAO_AUTOR')->nullable();
            $table->smallInteger('STATUS_AUTOR')->default(0);
            $table->dateTime('DATA_CRIACAO_AUTOR');
            $table->string('URL_IMAGEM_AUTOR', 200)->nullable();
            $table->string('LINK_AUTOR', 50);
            $table->unsignedInteger('ID_PESSOA');
        });
        Schema::create('PESSOA_pena', function (Blueprint $table) {
            $table->integer('ID_PESSOA')->primary();
            $table->string('NOME_PESSOA', 50);
            $table->string('SOBRENOME_PESSOA', 50);
        });
        Schema::create('IMAGENS_pena', function (Blueprint $table) {
            $table->integer('ID_IMAGENS')->primary();
            $table->string('ENDERECO_IMAGENS', 254)->unique();
            $table->smallInteger('STATUS_IMAGENS')->default(0);
            $table->string('NOME_IMAGENS', 30);
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

        (require database_path('migrations/2026_10_05_000002_add_admin_access_controls.php'))->up();

        DB::table('PESSOA_pena')->insert(['ID_PESSOA' => 1, 'NOME_PESSOA' => 'Pessoa', 'SOBRENOME_PESSOA' => 'Sintética']);
        DB::table('AUTOR_pena')->insert([
            'ID_AUTOR' => 9, 'ID_IMAGENS' => null, 'ASSINATURA_AUTOR' => 'Autoria sintética',
            'DESCRICAO_AUTOR' => 'Fixture de teste', 'STATUS_AUTOR' => 0,
            'DATA_CRIACAO_AUTOR' => '2026-01-01 00:00:00', 'URL_IMAGEM_AUTOR' => null,
            'LINK_AUTOR' => 'autoria-sintetica', 'ID_PESSOA' => 1,
        ]);
        DB::table('POST_pena')->insert([
            ['ID_POST' => 1, 'TITULO_POST' => 'Publicado', 'STATUS_POST' => 'PP', 'ID_AUTOR' => 9, 'ID_PESSOA' => 1],
            ['ID_POST' => 2, 'TITULO_POST' => 'Rascunho', 'STATUS_POST' => 'PO', 'ID_AUTOR' => 0, 'ID_PESSOA' => 1],
        ]);
        (require database_path('migrations/2026_10_05_000004_create_author_media_library.php'))->up();
    }

    protected function admin(): AdminUser
    {
        $admin = AdminUser::create(['name' => 'Teste', 'email' => 'teste@example.com', 'password' => 'senha-de-teste-123']);
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession(['pena_auth_version' => $user->auth_version]);
    }

    protected function installOrderTable(): void
    {
        (require database_path('migrations/2026_10_05_000001_create_pena_post_order_table.php'))->up();
        (require database_path('migrations/2026_10_05_000003_create_editorial_order_state_and_audit.php'))->up();
    }
}
