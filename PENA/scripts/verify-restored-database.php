<?php

// Optional read-only integration check, not part of the synthetic PHPUnit suite.
// Run only in a network-disabled container sharing the isolated MariaDB socket.
use App\Repositories\LegacyPostRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') ||
    config('database.default') !== 'mysql' ||
    config('database.connections.mysql.database') !== 'pena_audit' ||
    config('database.connections.mysql.unix_socket') !== '/run/mysqld/mysqld.sock' ||
    config('database.connections.mysql.url')) {
    throw new RuntimeException('Only the isolated pena_audit socket database is permitted.');
}

$repository = $app->make(LegacyPostRepository::class);
$posts = $repository->published();
$expected = DB::table('POST_pena')->where('STATUS_POST', 'PP')->pluck('ID_POST')
    ->map(fn ($id) => (int) $id)->sort()->values()->all();
$actual = array_column($posts, 'id');
sort($actual, SORT_NUMERIC);
if ($actual !== $expected || count(array_unique($actual)) !== count($actual)) {
    throw new RuntimeException('Public list omitted or duplicated published IDs.');
}

foreach ($posts as $post) {
    if ($post['status'] !== 'PP' || $repository->findPublished($post['id']) !== $post) {
        throw new RuntimeException('Public detail contract differs from the public list.');
    }
    if (! is_string($post['slug']) || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $post['slug'])) {
        throw new RuntimeException('A published slug requires editorial review.');
    }
}
if (count(array_unique(array_column($posts, 'slug'))) !== count($posts)) {
    throw new RuntimeException('Published slugs must be unique.');
}

$privateIds = DB::table('POST_pena')->where('STATUS_POST', '<>', 'PP')->pluck('ID_POST');
foreach ($privateIds as $id) {
    if ($repository->findPublished((int) $id) !== null) {
        throw new RuntimeException('A non-public article was exposed.');
    }
}
if ($repository->findPublished(2147483647) !== null) {
    throw new RuntimeException('An unknown ID must not return an article.');
}

// Throws on invalid UTF-8; never print article bodies, identities or credentials.
json_encode(['data' => $posts], JSON_THROW_ON_ERROR);
echo json_encode([
    'result' => 'PASS',
    'published_list_and_details' => count($posts),
    'non_public_details_rejected' => count($privateIds),
    'unknown_id_rejected' => true,
    'json_encoding' => 'valid',
    'published_slugs' => 'valid and unique',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
