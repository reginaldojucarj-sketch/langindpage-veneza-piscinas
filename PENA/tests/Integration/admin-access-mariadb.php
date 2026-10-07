<?php

/** Standalone, synthetic, socket-only test. Never run on an existing database. */
use App\Models\AdminUser;
use App\Services\AdminAccounts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (! app()->environment('testing') || config('database.default') !== 'mysql' ||
    $config['database'] !== 'pena_access_test' || $config['unix_socket'] !== '/run/mysqld/mysqld.sock' ||
    ! empty($config['url']) || getenv('PENA_SYNTHETIC_ACCESS_TEST') !== '1') {
    throw new RuntimeException('Refusing to run outside the isolated synthetic test database.');
}
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

if (($argv[1] ?? '') === 'worker') {
    $user = AdminUser::findOrFail((int) $argv[2]);
    $input = $user->only(['name', 'email', 'role', 'is_active', 'legacy_person_id']);
    $input['expected_version'] = $user->auth_version;
    $input[$argv[3]] = $argv[3] === 'role' ? 'editor' : false;
    echo "READY\n";
    flush();
    try {
        app(AdminAccounts::class)->update($user, $user->id, $input);
        echo "UPDATED\n";
    } catch (ValidationException $error) {
        check(isset($error->errors()['role']), 'Unexpected validation error.');
        echo "PROTECTED\n";
    }
    exit(0);
}

check(Schema::getTables() === [], 'Database must be empty; refusing to overwrite data.');
// Reproduce a legacy host whose default is MyISAM; new account tables must override it.
DB::statement('SET SESSION default_storage_engine=MyISAM');
Schema::create('PESSOA_pena', function (Blueprint $table) {
    $table->engine = 'MyISAM';
    $table->unsignedInteger('ID_PESSOA')->primary();
});
DB::table('PESSOA_pena')->insert(['ID_PESSOA' => 700]);
(require __DIR__.'/../../database/migrations/2026_10_05_000000_create_pena_admin_users_table.php')->up();
$adminEngine = DB::selectOne("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pena_admin_users'");
check(strtolower($adminEngine?->engine ?? '') === 'innodb', 'Administrative accounts must use InnoDB despite a MyISAM server default.');
DB::table('pena_admin_users')->insert([
    ['id' => 1, 'name' => 'Synthetic A', 'email' => 'a@example.invalid', 'password' => 'synthetic-unusable-hash'],
    ['id' => 2, 'name' => 'Synthetic B', 'email' => 'b@example.invalid', 'password' => 'synthetic-unusable-hash'],
]);
(require __DIR__.'/../../database/migrations/2026_10_05_000002_add_admin_access_controls.php')->up();
check(AdminUser::where('role', 'admin')->where('is_active', true)->count() === 2, 'Existing admins not preserved.');
check(AdminUser::find(1)->password === 'synthetic-unusable-hash', 'Migration changed existing password.');

foreach (['is_active', 'role'] as $field) {
    for ($round = 0; $round < 5; $round++) {
        DB::table('pena_admin_users')->update(['role' => 'admin', 'is_active' => true, 'auth_version' => 1]);
        $auditBefore = DB::table('pena_admin_audit')->count();
        // Hold the common lock so both independent connections contend for it.
        DB::beginTransaction();
        DB::table('pena_admin_access_lock')->where('id', 1)->lockForUpdate()->first();
        $workers = [];
        try {
            foreach ([1, 2] as $id) {
                $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $id, $field], base_path(), null, null, 20);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 10;
            do {
                $ready = count(array_filter($workers, fn ($worker) => str_contains($worker->getOutput(), 'READY')));
                if ($ready === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            check($ready === 2, 'Workers did not reach the concurrent barrier.');
            usleep(200000);
            foreach ($workers as $worker) {
                check($worker->isRunning() && ! str_contains($worker->getOutput(), 'UPDATED'), 'Write bypassed the held InnoDB lock.');
            }
            DB::commit();
            foreach ($workers as $worker) {
                check($worker->wait() === 0, 'Worker failed; investigate locally without publishing logs.');
            }
            $outputs = implode('', array_map(fn ($worker) => $worker->getOutput(), $workers));
            check(substr_count($outputs, 'UPDATED') === 1 && substr_count($outputs, 'PROTECTED') === 1, 'Expected one update and one last-admin rejection.');
            check(AdminUser::where('role', 'admin')->where('is_active', true)->count() === 1, 'Last admin was lost.');
            check(DB::table('pena_admin_audit')->count() === $auditBefore + 1, 'Audit must commit exactly once.');
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
}
check(DB::table('PESSOA_pena')->pluck('ID_PESSOA')->all() === [700], 'Synthetic legacy record changed.');
$engine = DB::selectOne("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'PESSOA_pena'");
check(strtolower($engine->engine) === 'myisam', 'Legacy engine was converted.');
echo "PASS: additive migration; 10 races (deactivation/demotion); last admin and audit preserved; legacy MyISAM untouched.\n";
