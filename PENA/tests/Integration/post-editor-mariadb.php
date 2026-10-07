<?php

/** Synthetic MariaDB-only editorial test. Never point this at a restored or live database. */
use App\Exceptions\ContentUnavailable;
use App\Exceptions\PostConflict;
use App\Models\AdminUser;
use App\Repositories\LegacyPostRepository;
use App\Services\ContentAudit;
use App\Services\PostEditor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (! app()->environment('testing') || config('database.default') !== 'mysql' ||
    $config['database'] !== 'pena_posts_test' || $config['unix_socket'] !== '/run/mysqld/mysqld.sock' ||
    ! empty($config['url']) || getenv('PENA_SYNTHETIC_POSTS_TEST') !== '1') {
    throw new RuntimeException('Refusing to run outside the isolated synthetic posts database.');
}
config()->set('pena.editorial_writes_enabled', true);

function checkPost(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function postInput(string $title, string $slug, int $authorId, ?string $fingerprint = null): array
{
    return [
        'title' => $title, 'slug' => $slug, 'html' => '<p>Conteúdo sintético seguro.</p>',
        'snippet' => 'Resumo sintético', 'description' => '', 'keywords' => '', 'cover_url' => '',
        'author_id' => $authorId, 'media_id' => null, 'main_category_id' => 1,
        'category_ids' => [1], 'expected_fingerprint' => $fingerprint,
    ];
}

if (($argv[1] ?? '') === 'worker') {
    [, , $postId, $actorId, $authorId, $token, $title, $barrier] = $argv;
    $actor = AdminUser::query()->findOrFail((int) $actorId);
    echo "READY\n";
    flush();
    $deadline = microtime(true) + 30;
    while (! is_file($barrier) && microtime(true) < $deadline) {
        usleep(10_000);
    }
    checkPost(is_file($barrier), 'Worker did not receive the concurrency barrier.');
    try {
        app(PostEditor::class)->update($actor, (int) $postId, postInput($title, 'artigo-de-prova', (int) $authorId, $token));
        echo "SAVED\n";
    } catch (PostConflict) {
        echo "CONFLICT\n";
    }
    exit(0);
}

checkPost(Schema::getTables() === [], 'Database must be empty; refusing to overwrite data.');
Schema::create('PESSOA_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->unsignedMediumInteger('ID_PESSOA')->primary();
    $table->string('NOME_PESSOA', 50);
    $table->string('SOBRENOME_PESSOA', 50);
});
Schema::create('AUTOR_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->unsignedMediumInteger('ID_AUTOR')->primary();
    $table->unsignedInteger('ID_IMAGENS')->nullable();
    $table->string('ASSINATURA_AUTOR', 50);
    $table->text('DESCRICAO_AUTOR')->nullable();
    $table->smallInteger('STATUS_AUTOR');
    $table->dateTime('DATA_CRIACAO_AUTOR');
    $table->string('URL_IMAGEM_AUTOR', 200)->nullable();
    $table->string('LINK_AUTOR', 50);
    $table->unsignedMediumInteger('ID_PESSOA');
});
Schema::create('CATEGORIA_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->unsignedMediumInteger('ID_CATEGORIA')->primary();
    $table->string('NOME_CATEGORIA', 100);
});
Schema::create('IMAGENS_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->unsignedInteger('ID_IMAGENS')->primary();
    $table->string('ENDERECO_IMAGENS', 200);
    $table->string('NOME_IMAGENS', 100);
});
Schema::create('POST_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->charset = 'utf8mb3';
    $table->collation = 'utf8mb3_general_ci';
    $table->increments('ID_POST');
    $table->string('TITULO_POST', 71);
    $table->string('LINK_POST', 200)->unique();
    $table->longText('CONTEUDO_POST')->nullable();
    $table->text('DESCRICAO_POST')->nullable();
    $table->string('SNIPPET_POST', 156)->nullable();
    $table->string('STATUS_POST', 8);
    $table->string('DESTAQUE_POST', 1)->nullable();
    $table->string('KEYWORDS_POST', 200)->nullable();
    $table->string('URL_IMAGEM_POST', 200)->nullable();
    $table->unsignedMediumInteger('ID_PESSOA');
    $table->unsignedMediumInteger('ID_AUTOR')->nullable();
    $table->unsignedMediumInteger('ID_CATEGORIA')->nullable();
    $table->unsignedInteger('ID_IMAGENS')->nullable();
    $table->dateTime('DATA_CRIACAO_POST')->nullable();
    $table->dateTime('DATA_POSTAGEM_POST')->nullable();
    $table->dateTime('DATA_ULTIMA_MODIFICACAO_POST')->nullable();
});
DB::statement("ALTER TABLE POST_pena MODIFY STATUS_POST SET('PE','PO','PP','PR') NOT NULL");
Schema::create('CATEGORIA_POST_pena', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->unsignedInteger('ID_POST');
    $table->unsignedMediumInteger('ID_CATEGORIA');
    $table->unique(['ID_POST', 'ID_CATEGORIA']);
});
Schema::create('pena_admin_users', function (Blueprint $table) {
    $table->engine = 'InnoDB';
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
DB::table('PESSOA_pena')->insert(['ID_PESSOA' => 1, 'NOME_PESSOA' => 'Pessoa', 'SOBRENOME_PESSOA' => 'Sintética']);
DB::table('AUTOR_pena')->insert([
    'ID_AUTOR' => 9, 'ID_PESSOA' => 1, 'ID_IMAGENS' => null, 'ASSINATURA_AUTOR' => 'Autor sintético',
    'DESCRICAO_AUTOR' => '', 'STATUS_AUTOR' => 0, 'DATA_CRIACAO_AUTOR' => '2026-01-01 00:00:00',
    'URL_IMAGEM_AUTOR' => null, 'LINK_AUTOR' => 'autor-sintetico',
]);
DB::table('CATEGORIA_pena')->insert(['ID_CATEGORIA' => 1, 'NOME_CATEGORIA' => 'Categoria sintética']);
(require __DIR__.'/../../database/migrations/2026_10_05_000002_add_admin_access_controls.php')->up();
(require __DIR__.'/../../database/migrations/2026_10_05_000001_create_pena_post_order_table.php')->up();
(require __DIR__.'/../../database/migrations/2026_10_05_000003_create_editorial_order_state_and_audit.php')->up();
(require __DIR__.'/../../database/migrations/2026_10_05_000004_create_author_media_library.php')->up();
$userId = DB::table('pena_admin_users')->insertGetId([
    'name' => 'Admin sintético', 'email' => 'posts-test@example.invalid', 'password' => Hash::make('synthetic-password'),
    'role' => 'admin', 'is_active' => true, 'auth_version' => 1, 'legacy_person_id' => 1,
    'created_at' => now(), 'updated_at' => now(),
]);
$actor = AdminUser::query()->findOrFail($userId);
$authorId = (int) DB::table('pena_authors')->value('id');
$editor = app(PostEditor::class);
checkPost($editor->canWrite(), 'InnoDB-only synthetic schema should allow explicitly enabled writes.');
$postId = $editor->create($actor, postInput('Artigo de prova', 'artigo-de-prova', $authorId));
checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->where('STATUS_POST', 'PO')->exists(), 'Created article must start hidden.');
checkPost(DB::table('CATEGORIA_POST_pena')->where('ID_POST', $postId)->count() === 1, 'Category link was not saved.');
$token = $editor->find($postId)['fingerprint'];
$barrier = sys_get_temp_dir().'/pena-post-race-'.bin2hex(random_bytes(8));
$workers = [];
try {
    foreach (['Primeira edição', 'Segunda edição'] as $title) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $postId, (string) $userId, (string) $authorId, $token, $title, $barrier], base_path(), null, null, 40);
        $process->start();
        $workers[] = $process;
    }
    $deadline = microtime(true) + 20;
    do {
        $ready = count(array_filter($workers, fn (Process $worker) => str_contains($worker->getOutput(), 'READY')));
        if ($ready === 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    checkPost($ready === 2, 'Concurrent editors did not reach the barrier.');
    file_put_contents($barrier, 'go');
    foreach ($workers as $worker) {
        checkPost($worker->wait() === 0, 'Concurrent edit worker failed: '.$worker->getErrorOutput());
    }
    $output = implode('', array_map(fn (Process $worker) => $worker->getOutput(), $workers));
    checkPost(substr_count($output, 'SAVED') === 1 && substr_count($output, 'CONFLICT') === 1, 'Concurrent edits must yield one save and one conflict.');
    checkPost(DB::table('pena_content_audit')->where('action', 'post.updated')->count() === 1, 'Only the committed edit may have a success audit.');
} finally {
    @unlink($barrier);
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
}
$maxDescription = str_repeat('á', 32767).'a';
checkPost(strlen($maxDescription) === PostEditor::DESCRIPTION_MAX_BYTES, 'Synthetic description boundary is incorrect.');
$withDescription = postInput('Descrição no limite', 'artigo-de-prova', $authorId, $editor->find($postId)['fingerprint']);
$withDescription['description'] = $maxDescription;
$editor->update($actor, $postId, $withDescription);
checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->value('DESCRICAO_POST') === $maxDescription, 'MariaDB TEXT did not retain a 65,535-byte description.');
$tooLong = postInput('Descrição excedente', 'artigo-de-prova', $authorId, $editor->find($postId)['fingerprint']);
$tooLong['description'] = str_repeat('á', 32768);
try {
    $editor->update($actor, $postId, $tooLong);
    throw new RuntimeException('Description larger than TEXT unexpectedly saved.');
} catch (ValidationException $error) {
    checkPost(isset($error->errors()['description']), 'Oversized description must return a field validation error.');
}
checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->value('DESCRICAO_POST') === $maxDescription, 'Rejected description changed the stored value.');
$editor->update($actor, $postId, postInput('Artigo 100%_!', 'artigo-de-prova', $authorId, $editor->find($postId)['fingerprint']));
$otherPostId = $editor->create($actor, postInput('Artigo comum', 'artigo-comum', $authorId));
foreach (['%', '_', '!'] as $literal) {
    $listed = $editor->listing($literal, null);
    checkPost($listed->total() === 1 && $listed->items()[0]['ID_POST'] === $postId, 'Administrative search treated '.$literal.' as a wildcard.');
}
$editor->transition($actor, $postId, 'publish', $editor->find($postId)['fingerprint']);
$editor->transition($actor, $otherPostId, 'publish', $editor->find($otherPostId)['fingerprint']);
foreach (['%', '_', '!'] as $literal) {
    $listed = app(LegacyPostRepository::class)->publishedPage(1, 25, $literal);
    checkPost($listed['meta']['total'] === 1 && $listed['data'][0]['id'] === $postId, 'Public search treated '.$literal.' as a wildcard.');
}
checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->where('STATUS_POST', 'PP')->exists(), 'Publish did not persist.');
$editor->transition($actor, $postId, 'hide', $editor->find($postId)['fingerprint']);
$editor->transition($actor, $otherPostId, 'hide', $editor->find($otherPostId)['fingerprint']);
checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->where('STATUS_POST', 'PO')->exists(), 'Hide did not persist.');
DB::table('CATEGORIA_pena')->insert(['ID_CATEGORIA' => 2, 'NOME_CATEGORIA' => 'Outra categoria sintética']);
$before = DB::table('POST_pena')->where('ID_POST', $postId)->value('TITULO_POST');
$beforeAudit = DB::table('pena_content_audit')->count();
$failingAudit = new class extends ContentAudit
{
    public function record(?int $actorId, string $action, string $entityType, string|int|null $entityId, array $summary = []): string
    {
        throw new RuntimeException('synthetic audit failure');
    }
};
app()->instance(ContentAudit::class, $failingAudit);
try {
    $changed = postInput('Não pode persistir', 'artigo-de-prova', $authorId, $editor->find($postId)['fingerprint']);
    $changed['category_ids'] = [1, 2];
    app(PostEditor::class)->update($actor, $postId, $changed);
    throw new RuntimeException('Injected audit failure did not abort the edit.');
} catch (ContentUnavailable) {
    checkPost(DB::table('POST_pena')->where('ID_POST', $postId)->value('TITULO_POST') === $before, 'Failed edit left a changed legacy row.');
    checkPost(DB::table('CATEGORIA_POST_pena')->where('ID_POST', $postId)->count() === 1, 'Failed edit left changed category links.');
    checkPost(DB::table('pena_content_audit')->count() === $beforeAudit, 'Failed edit recorded success.');
}
DB::statement('ALTER TABLE POST_pena ENGINE=MyISAM');
checkPost(! $editor->canWrite(), 'MyISAM source must block editorial writes.');
try {
    $editor->create($actor, postInput('Deve falhar', 'deve-falhar', $authorId));
    throw new RuntimeException('MyISAM write unexpectedly succeeded.');
} catch (ContentUnavailable) {
    checkPost(! DB::table('POST_pena')->where('LINK_POST', 'deve-falhar')->exists(), 'Blocked write changed MyISAM data.');
}

echo "PASS: InnoDB create/publish/hide; literal search; two MariaDB connections produce one edit/conflict; audit rollback; MyISAM write denied.\n";
