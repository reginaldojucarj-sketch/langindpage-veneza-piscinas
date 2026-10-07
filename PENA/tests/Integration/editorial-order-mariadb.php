<?php

/** Standalone synthetic MariaDB test; never point this script at a real database. */
use App\Exceptions\EditorialUnavailable;
use App\Exceptions\OrderConflict;
use App\Exceptions\PublishedListingChanged;
use App\Repositories\LegacyPostRepository;
use App\Services\EditorialOrdering;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (! app()->environment('testing') || config('database.default') !== 'mysql' ||
    $config['database'] !== 'pena_editorial_test' || $config['unix_socket'] !== '/run/mysqld/mysqld.sock' ||
    ! empty($config['url']) || getenv('PENA_SYNTHETIC_EDITORIAL_TEST') !== '1') {
    throw new RuntimeException('Refusing to run outside the isolated synthetic editorial database.');
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function resetFixture(array $publishedIds = [1, 2]): void
{
    DB::table('pena_post_order')->delete();
    DB::table('pena_editorial_audit')->delete();
    DB::table('pena_editorial_state')->where('id', 1)->update([
        'revision' => 1,
        'published_digest' => hash('sha256', ''),
        'updated_at' => now(),
    ]);
    DB::table('POST_pena')->delete();
    foreach ($publishedIds as $id) {
        DB::table('POST_pena')->insert(['ID_POST' => $id, 'STATUS_POST' => 'PP']);
    }
}

if (($argv[1] ?? '') === 'worker') {
    [$ignored, $mode, $idsCsv, $key] = $argv;
    $ids = array_map('intval', explode(',', $idsCsv));
    echo "READY\n";
    flush();
    try {
        $result = app(EditorialOrdering::class)->save($ids, 1, 44, $key, (string) Str::uuid());
        echo $result['replayed'] ? "REPLAYED\n" : "SAVED\n";
    } catch (OrderConflict) {
        echo "CONFLICT\n";
    }
    exit(0);
}

check(Schema::getTables() === [], 'Database must be empty; refusing to overwrite data.');
Schema::create('POST_pena', function (Blueprint $table) {
    $table->engine = 'MyISAM';
    $table->unsignedInteger('ID_POST')->primary();
    $table->string('TITULO_POST')->nullable();
    $table->longText('CONTEUDO_POST')->nullable();
    $table->longText('DESCRICAO_POST')->nullable();
    $table->longText('SNIPPET_POST')->nullable();
    $table->string('STATUS_POST', 2);
    $table->string('DESTAQUE_POST')->nullable();
    $table->dateTime('DATA_POSTAGEM_POST')->nullable();
    $table->dateTime('DATA_CRIACAO_POST')->nullable();
    $table->dateTime('DATA_ULTIMA_MODIFICACAO_POST')->nullable();
    $table->string('LINK_POST')->nullable();
    $table->text('KEYWORDS_POST')->nullable();
    $table->text('URL_IMAGEM_POST')->nullable();
    $table->unsignedInteger('ID_AUTOR')->nullable();
    $table->unsignedInteger('ID_PESSOA')->nullable();
    $table->unsignedInteger('ID_IMAGENS')->nullable();
    $table->unsignedInteger('ID_CATEGORIA')->nullable();
});
Schema::create('AUTOR_pena', function (Blueprint $table) {
    $table->unsignedInteger('ID_AUTOR')->primary();
    $table->string('ASSINATURA_AUTOR')->nullable();
});
Schema::create('PESSOA_pena', function (Blueprint $table) {
    $table->unsignedInteger('ID_PESSOA')->primary();
    $table->string('NOME_PESSOA')->nullable();
    $table->string('SOBRENOME_PESSOA')->nullable();
});
Schema::create('IMAGENS_pena', function (Blueprint $table) {
    $table->unsignedInteger('ID_IMAGENS')->primary();
    $table->text('ENDERECO_IMAGENS')->nullable();
    $table->string('NOME_IMAGENS')->nullable();
});
Schema::create('CATEGORIA_pena', function (Blueprint $table) {
    $table->unsignedInteger('ID_CATEGORIA')->primary();
    $table->string('NOME_CATEGORIA')->nullable();
});
Schema::create('CATEGORIA_POST_pena', function (Blueprint $table) {
    $table->unsignedInteger('ID_POST');
    $table->unsignedInteger('ID_CATEGORIA');
});
(require __DIR__.'/../../database/migrations/2026_10_05_000001_create_pena_post_order_table.php')->up();
(require __DIR__.'/../../database/migrations/2026_10_05_000003_create_editorial_order_state_and_audit.php')->up();
$engineRows = DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
$engines = collect($engineRows)->keyBy('name')->map(fn ($row) => strtolower($row->engine));
check($engines->get('POST_pena') === 'myisam', 'Synthetic legacy table must stay MyISAM.');
foreach (['pena_post_order', 'pena_editorial_state', 'pena_editorial_audit'] as $name) {
    check($engines->get($name) === 'innodb', "{$name} must be InnoDB.");
}

// Fail closed if an auxiliary table has been manually changed to MyISAM.
resetFixture();
DB::statement('ALTER TABLE pena_post_order ENGINE=MyISAM');
try {
    app(EditorialOrdering::class)->save([1, 2], 1, 44, (string) Str::uuid(), (string) Str::uuid());
    check(false, 'A MyISAM order table must be rejected before writes.');
} catch (EditorialUnavailable) {
    check(DB::table('pena_editorial_state')->where('id', 1)->value('revision') === 1, 'Unavailable storage changed state.');
    check(DB::table('pena_post_order')->count() === 0, 'Unavailable storage wrote order rows.');
    check(DB::table('pena_editorial_audit')->where('outcome', 'failure')->count() === 1, 'Unavailable storage was not audited.');
}
DB::statement('ALTER TABLE pena_post_order ENGINE=InnoDB');
resetFixture();

// Ten independent-connection races; the InnoDB singleton serializes writers.
for ($round = 0; $round < 10; $round++) {
    resetFixture();
    DB::beginTransaction();
    DB::table('pena_editorial_state')->where('id', 1)->lockForUpdate()->first();
    $workers = [];
    try {
        foreach ([['1,2', (string) Str::uuid()], ['2,1', (string) Str::uuid()]] as [$ids, $key]) {
            $worker = new Process([PHP_BINARY, __FILE__, 'worker', $ids, $key], base_path(), null, null, 30);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 20;
        do {
            $ready = count(array_filter($workers, fn ($worker) => str_contains($worker->getOutput(), 'READY')));
            if ($ready === 2) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        check($ready === 2, 'Concurrent workers did not reach the barrier.');
        usleep(150000);
        foreach ($workers as $worker) {
            check($worker->isRunning() && ! str_contains($worker->getOutput(), 'SAVED'), 'A writer bypassed the InnoDB mutex.');
        }
        DB::commit();
        foreach ($workers as $worker) {
            check($worker->wait() === 0, 'An independent MariaDB worker failed.');
        }
        $output = implode('', array_map(fn ($worker) => $worker->getOutput(), $workers));
        check(substr_count($output, 'SAVED') === 1 && substr_count($output, 'CONFLICT') === 1, 'Expected one save and one 409 conflict.');
        check((int) DB::table('pena_editorial_state')->where('id', 1)->value('revision') === 2, 'Revision did not advance exactly once.');
        check(DB::table('pena_post_order')->count() === 2, 'The successful order is incomplete or duplicated.');
        check(DB::table('pena_editorial_audit')->where('outcome', 'success')->count() === 1, 'Expected one successful audit row.');
        check(DB::table('pena_editorial_audit')->where('outcome', 'conflict')->count() === 1, 'Expected one conflict audit row.');
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
    }
}

// Force a failure after each InnoDB mutation and prove rollback on real MariaDB.
resetFixture();
$ordering = app(EditorialOrdering::class);
$checkpoints = ['after_published_set_check', 'after_order_delete', 'after_order_insert', 'after_state_update', 'before_success_audit', 'after_success_audit'];
foreach ($checkpoints as $checkpoint) {
    try {
        $ordering->save([1, 2], 1, 44, (string) Str::uuid(), (string) Str::uuid(),
            function (string $current) use ($checkpoint): void {
                if ($current === $checkpoint) {
                    throw new RuntimeException('synthetic write failure');
                }
            });
        check(false, 'Injected failure did not escape.');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'synthetic write failure', 'Unexpected injected failure.');
    }
    check((int) DB::table('pena_editorial_state')->where('id', 1)->value('revision') === 1, "Revision leaked after {$checkpoint}.");
    check(DB::table('pena_post_order')->count() === 0, "Order rows leaked after {$checkpoint}.");
}
check(DB::table('pena_editorial_audit')->where('outcome', 'success')->count() === 0, 'Failed operations must not have success audits.');
check(DB::table('pena_editorial_audit')->where('outcome', 'failure')->count() === count($checkpoints), 'Failure audit rows were not written after rollback.');

// Exercise Laravel's deadlock retry path with a driver-shaped deadlock error.
// The independent-connection races above test real MariaDB lock contention.
resetFixture();
$attempts = 0;
$retryResult = $ordering->save([1, 2], 1, 44, (string) Str::uuid(), (string) Str::uuid(),
    function (string $current) use (&$attempts): void {
        if ($current === 'after_published_set_check' && $attempts++ === 0) {
            throw new QueryException('mysql', 'UPDATE synthetic_deadlock', [], new PDOException('Deadlock found when trying to get lock'));
        }
    });
check($attempts === 2 && $retryResult['revision'] === 2, 'A deadlock error did not retry exactly once.');
check(DB::table('pena_post_order')->count() === 2, 'Deadlock retry produced duplicate or incomplete order rows.');
check(DB::table('pena_editorial_audit')->where('outcome', 'success')->count() === 1, 'Deadlock retry duplicated success audit.');
check(DB::table('pena_editorial_audit')->where('outcome', 'failure')->count() === 0, 'Recovered deadlock was recorded as a failed operation.');

// Retry-safe write: same actor/key/payload returns the committed revision once.
resetFixture();
$idempotencyKey = (string) Str::uuid();
$first = $ordering->save([2, 1], 1, 44, $idempotencyKey, (string) Str::uuid());
$retry = $ordering->save([2, 1], 1, 44, $idempotencyKey, (string) Str::uuid());
check($first['revision'] === 2 && ! $first['replayed'] && $retry['revision'] === 2 && $retry['replayed'], 'Idempotent retry did not return the original result.');
check(DB::table('pena_editorial_audit')->where('outcome', 'success')->count() === 1, 'Idempotent retry duplicated the success audit.');

// A legacy writer can publish during an ordering save because it ignores the
// InnoDB mutex; the stored set digest must then make the manual order inapplicable.
resetFixture();
$ordering->save([1, 2], 1, 44, (string) Str::uuid(), (string) Str::uuid(),
    function (string $current): void {
        if ($current === 'after_published_set_check') {
            DB::table('POST_pena')->insert(['ID_POST' => 3, 'STATUS_POST' => 'PP']);
        }
    });
check($ordering->currentOrder([1, 2, 3]) === null, 'Manual order must be ignored after a legacy publication changes the set.');
check(DB::table('POST_pena')->count() === 3, 'Synthetic MyISAM legacy write should remain visible.');

// The audit intentionally carries identifiers/digests and count, never article HTML.
$summary = DB::table('pena_editorial_audit')->where('outcome', 'success')->value('summary');
check(! str_contains((string) $summary, '<p>') && ! str_contains((string) $summary, 'conteudo'), 'Audit must not contain article HTML.');

// A MyISAM writer may unpublish an ID after the first listing read. The API
// must not return its metadata or silently present a partial page as stable.
resetFixture([1]);
$ordering->save([1], 1, 44, (string) Str::uuid(), (string) Str::uuid());
$unpublishedDuringRead = false;
DB::listen(function (QueryExecuted $query) use (&$unpublishedDuringRead): void {
    $sql = strtolower(preg_replace('/[`"]/', '', $query->sql));
    if (! $unpublishedDuringRead && preg_match('/select\\s+id_post\\s+from\\s+post_pena\\s+where\\s+status_post\\s*=\\s*\\?/', $sql)) {
        $unpublishedDuringRead = true;
        DB::table('POST_pena')->where('ID_POST', 1)->update(['STATUS_POST' => 'PO']);
    }
});
try {
    app(LegacyPostRepository::class)->publishedPage(1, 50);
    check(false, 'A changed MyISAM publication set must abort the page read.');
} catch (PublishedListingChanged) {
    check($unpublishedDuringRead, 'The synthetic legacy writer did not run between listing reads.');
    check(DB::table('POST_pena')->where('STATUS_POST', 'PP')->count() === 0, 'The MyISAM status change did not persist.');
}

echo "PASS: InnoDB order mutex; 10 two-connection races; six MariaDB rollback checkpoints; deadlock retry; idempotency; MyISAM write/read drift; redacted audit.\n";
