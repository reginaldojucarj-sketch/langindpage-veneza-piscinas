<?php

/** Disposable SQLite fixture for protected Swagger and the complete editorial browser flow. */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$database = getenv('DB_DATABASE');
if (getenv('PENA_SYNTHETIC_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing' ||
    getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_URL') ||
    getenv('PENA_EDITORIAL_WRITES_ENABLED') !== 'true' || $database !== '/tmp/pena-browser-test.sqlite') {
    throw new RuntimeException('Browser fixture refused: isolated SQLite test configuration is required.');
}

if (file_exists($database)) {
    if (! is_file($database) || filesize($database) !== 0) {
        throw new RuntimeException('Browser fixture refused: temporary database is not empty.');
    }
} elseif (! touch($database)) {
    throw new RuntimeException('Could not create the temporary browser-test database.');
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== $database || Schema::hasTable('pena_admin_users')) {
    throw new RuntimeException('Browser fixture refused: database is not empty isolated SQLite.');
}

Schema::create('POST_pena', function (Blueprint $table): void {
    $table->increments('ID_POST');
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
Schema::create('PESSOA_pena', function (Blueprint $table): void {
    $table->integer('ID_PESSOA')->primary();
    $table->string('NOME_PESSOA');
    $table->string('SOBRENOME_PESSOA');
});
Schema::create('AUTOR_pena', function (Blueprint $table): void {
    $table->integer('ID_AUTOR')->primary();
    $table->integer('ID_PESSOA');
    $table->integer('ID_IMAGENS')->nullable();
    $table->string('ASSINATURA_AUTOR');
    $table->string('LINK_AUTOR');
    $table->text('DESCRICAO_AUTOR')->nullable();
    $table->string('URL_IMAGEM_AUTOR')->nullable();
    $table->dateTime('DATA_CRIACAO_AUTOR');
});
Schema::create('IMAGENS_pena', function (Blueprint $table): void {
    $table->integer('ID_IMAGENS')->primary();
    $table->string('ENDERECO_IMAGENS');
    $table->string('NOME_IMAGENS');
});
Schema::create('CATEGORIA_pena', function (Blueprint $table): void {
    $table->integer('ID_CATEGORIA')->primary();
    $table->string('NOME_CATEGORIA');
});
Schema::create('CATEGORIA_POST_pena', function (Blueprint $table): void {
    $table->integer('ID_POST');
    $table->integer('ID_CATEGORIA');
});
Schema::create('pena_admin_users', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

DB::table('PESSOA_pena')->insert(['ID_PESSOA' => 1, 'NOME_PESSOA' => 'Pessoa', 'SOBRENOME_PESSOA' => 'Sintética']);
DB::table('AUTOR_pena')->insert([
    'ID_AUTOR' => 9, 'ID_PESSOA' => 1, 'ID_IMAGENS' => null,
    'ASSINATURA_AUTOR' => 'Autoria sintética', 'LINK_AUTOR' => 'autoria-sintetica',
    'DESCRICAO_AUTOR' => 'Fixture de navegador', 'URL_IMAGEM_AUTOR' => null,
    'DATA_CRIACAO_AUTOR' => '2026-01-01 00:00:00',
]);
DB::table('CATEGORIA_pena')->insert(['ID_CATEGORIA' => 1, 'NOME_CATEGORIA' => 'Tratamento']);
(require database_path('migrations/2026_10_05_000001_create_pena_post_order_table.php'))->up();
(require database_path('migrations/2026_10_05_000002_add_admin_access_controls.php'))->up();
(require database_path('migrations/2026_10_05_000003_create_editorial_order_state_and_audit.php'))->up();
(require database_path('migrations/2026_10_05_000004_create_author_media_library.php'))->up();

DB::table('pena_admin_users')->insert([
    'name' => 'Browser Test Admin', 'email' => 'browser@example.test',
    'password' => Hash::make('Synthetic-browser-123!'),
    'role' => 'admin', 'is_active' => true, 'auth_version' => 1, 'legacy_person_id' => 1,
    'created_at' => now(), 'updated_at' => now(),
]);

echo "Disposable browser-test account ready.\n";
