<?php

/** Isolated synthetic MariaDB checks for legacy-safe author migration and media uniqueness. */
use App\Models\AdminUser;
use App\Services\AuthorLibrary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (! app()->environment('testing') || config('database.default') !== 'mysql' ||
    $config['database'] !== 'pena_content_test' || $config['unix_socket'] !== '/run/mysqld/mysqld.sock' ||
    ! empty($config['url']) || getenv('PENA_SYNTHETIC_CONTENT_TEST') !== '1') {
    throw new RuntimeException('Refusing to run outside the isolated synthetic content database.');
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function awaitBarrier(string $path): void
{
    $deadline = microtime(true) + 30;
    while (! is_file($path) && microtime(true) < $deadline) {
        usleep(10_000);
    }
    check(is_file($path), 'Worker did not receive the concurrency barrier.');
}

if (in_array(($argv[1] ?? ''), ['author-worker', 'media-worker'], true)) {
    if ($argv[1] === 'author-worker') {
        [, , $authorId, $actorId, $signature, $slug, $barrier] = $argv;
        $actor = AdminUser::query()->findOrFail((int) $actorId);
        echo "READY\n";
        flush();
        awaitBarrier($barrier);
        try {
            app(AuthorLibrary::class)->update($actor, (int) $authorId, [
                'expected_version' => 1, 'signature' => $signature, 'slug' => $slug,
            ]);
            echo "SAVED\n";
        } catch (ValidationException) {
            echo "CONFLICT\n";
        }
        exit(0);
    }

    [, , $uuid, $sha256, $actorId, $barrier] = $argv;
    echo "READY\n";
    flush();
    awaitBarrier($barrier);
    try {
        DB::table('pena_media')->insert([
            'id' => $uuid, 'sha256' => $sha256, 'original_name' => 'synthetic.png',
            'source_mime' => 'image/png', 'public_mime' => 'image/png', 'original_bytes' => 12,
            'width' => 1, 'height' => 1, 'alt_text' => 'Fixture sintética',
            'original_path' => $uuid.'.png', 'derivative_path' => $uuid.'.png',
            'is_active' => true, 'uploaded_by' => (int) $actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        echo "CREATED\n";
    } catch (QueryException $error) {
        if ((string) ($error->errorInfo[1] ?? '') !== '1062') {
            throw $error;
        }
        echo "DUPLICATE\n";
    }
    exit(0);
}

check(Schema::getTables() === [], 'Database must be empty; refusing to overwrite data.');
DB::statement('SET SESSION default_storage_engine=InnoDB');

Schema::create('POST_pena', function (Blueprint $table) {
    $table->engine = 'MyISAM';
    $table->unsignedInteger('ID_POST')->primary();
    $table->unsignedMediumInteger('ID_AUTOR')->nullable();
    $table->string('STATUS_POST', 2);
});
Schema::create('AUTOR_pena', function (Blueprint $table) {
    $table->engine = 'MyISAM';
    $table->unsignedMediumInteger('ID_AUTOR')->primary();
    $table->unsignedInteger('ID_IMAGENS')->nullable();
    $table->string('ASSINATURA_AUTOR', 50)->unique();
    $table->text('DESCRICAO_AUTOR')->nullable();
    $table->smallInteger('STATUS_AUTOR');
    $table->dateTime('DATA_CRIACAO_AUTOR');
    $table->string('URL_IMAGEM_AUTOR', 200)->nullable();
    $table->string('LINK_AUTOR', 50)->unique();
    $table->unsignedMediumInteger('ID_PESSOA');
});
Schema::create('PESSOA_pena', function (Blueprint $table) {
    $table->engine = 'MyISAM';
    $table->unsignedMediumInteger('ID_PESSOA')->primary();
    $table->string('NOME_PESSOA', 50);
    $table->string('SOBRENOME_PESSOA', 50);
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
    'ID_AUTOR' => 8, 'ID_IMAGENS' => null, 'ASSINATURA_AUTOR' => 'Assinatura histórica',
    'DESCRICAO_AUTOR' => 'Apenas fixture.', 'STATUS_AUTOR' => 0,
    'DATA_CRIACAO_AUTOR' => '2026-01-01 00:00:00', 'URL_IMAGEM_AUTOR' => null,
    'LINK_AUTOR' => 'assinatura-historica', 'ID_PESSOA' => 1,
]);
DB::table('POST_pena')->insert([
    ['ID_POST' => 101, 'ID_AUTOR' => 8, 'STATUS_POST' => 'PP'],
    ['ID_POST' => 102, 'ID_AUTOR' => 0, 'STATUS_POST' => 'PP'],
]);
(require __DIR__.'/../../database/migrations/2026_10_05_000002_add_admin_access_controls.php')->up();
(require __DIR__.'/../../database/migrations/2026_10_05_000004_create_author_media_library.php')->up();

DB::table('pena_admin_users')->insert([
    'name' => 'Admin sintético', 'email' => 'content-test@example.invalid', 'password' => Hash::make('synthetic-password'),
    'role' => 'admin', 'is_active' => true, 'auth_version' => 1,
    'created_at' => now(), 'updated_at' => now(),
]);
$author = DB::table('pena_authors')->where('legacy_author_id', 8)->first();
check($author !== null, 'Legacy author was not copied to the InnoDB editorial table.');
check(DB::table('pena_post_author_assignments')->where('post_id', 101)->value('author_signature_snapshot') === 'Assinatura histórica', 'Legacy author signature snapshot was not preserved.');
check(! DB::table('pena_post_author_assignments')->where('post_id', 102)->exists(), 'Orphan legacy author ID zero must not be inferred.');
check(DB::table('AUTOR_pena')->where('ID_AUTOR', 8)->value('ASSINATURA_AUTOR') === 'Assinatura histórica', 'Migration modified the legacy author table.');

$userId = (int) DB::table('pena_admin_users')->value('id');
$barrier = sys_get_temp_dir().'/pena-author-race-'.bin2hex(random_bytes(8));
$workers = [];
try {
    foreach ([['Concorrente A', 'concorrente-a'], ['Concorrente B', 'concorrente-b']] as [$signature, $slug]) {
        $process = new Process([PHP_BINARY, __FILE__, 'author-worker', (string) $author->id, (string) $userId, $signature, $slug, $barrier], base_path(), null, null, 30);
        $process->start();
        $workers[] = $process;
    }
    $deadline = microtime(true) + 20;
    do {
        $ready = count(array_filter($workers, fn (Process $process) => str_contains($process->getOutput(), 'READY')));
        if ($ready === 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    check($ready === 2, 'Author workers did not reach the concurrency barrier.');
    file_put_contents($barrier, 'go');
    foreach ($workers as $worker) {
        check($worker->wait() === 0, 'Author update worker failed.');
    }
    $outputs = implode('', array_map(fn (Process $worker) => $worker->getOutput(), $workers));
    check(substr_count($outputs, 'SAVED') === 1 && substr_count($outputs, 'CONFLICT') === 1, 'Concurrent stale author edits must produce one save and one conflict.');
    check((int) DB::table('pena_authors')->where('id', $author->id)->value('version') === 2, 'Concurrent author edits changed more than one revision.');
    check(DB::table('pena_content_audit')->where('action', 'author.updated')->count() === 1, 'Concurrent author edit created an invalid audit count.');
} finally {
    @unlink($barrier);
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
}

$mediaBarrier = sys_get_temp_dir().'/pena-media-race-'.bin2hex(random_bytes(8));
$mediaWorkers = [];
$sha256 = str_repeat('a', 64);
try {
    foreach ([Str::uuid()->toString(), Str::uuid()->toString()] as $uuid) {
        $process = new Process([PHP_BINARY, __FILE__, 'media-worker', $uuid, $sha256, (string) $userId, $mediaBarrier], base_path(), null, null, 30);
        $process->start();
        $mediaWorkers[] = $process;
    }
    $deadline = microtime(true) + 20;
    do {
        $ready = count(array_filter($mediaWorkers, fn (Process $process) => str_contains($process->getOutput(), 'READY')));
        if ($ready === 2) {
            break;
        }
        usleep(10_000);
    } while (microtime(true) < $deadline);
    check($ready === 2, 'Media workers did not reach the concurrency barrier.');
    file_put_contents($mediaBarrier, 'go');
    foreach ($mediaWorkers as $worker) {
        check($worker->wait() === 0, 'Media uniqueness worker failed.');
    }
    $outputs = implode('', array_map(fn (Process $worker) => $worker->getOutput(), $mediaWorkers));
    check(substr_count($outputs, 'CREATED') === 1 && substr_count($outputs, 'DUPLICATE') === 1, 'Concurrent identical media must keep exactly one metadata row.');
    check(DB::table('pena_media')->where('sha256', $sha256)->count() === 1, 'The InnoDB checksum constraint did not prevent duplicates.');
} finally {
    @unlink($mediaBarrier);
    foreach ($mediaWorkers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
}

$engines = collect(DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'))
    ->keyBy('name')->map(fn ($row) => strtolower($row->engine));
check($engines->get('POST_pena') === 'myisam' && $engines->get('AUTOR_pena') === 'myisam', 'Legacy tables must remain unchanged MyISAM.');
foreach (['pena_authors', 'pena_media', 'pena_post_author_assignments', 'pena_post_media_assignments', 'pena_content_audit'] as $table) {
    check($engines->get($table) === 'innodb', "{$table} must be InnoDB.");
}

echo "PASS: Legacy author snapshots and zero-ID handling; two concurrent author edits; duplicate-media checksum uniqueness; legacy MyISAM preserved.\n";
