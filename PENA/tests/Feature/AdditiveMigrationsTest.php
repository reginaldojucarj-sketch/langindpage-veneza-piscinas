<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyDatabaseTestCase;

class AdditiveMigrationsTest extends LegacyDatabaseTestCase
{
    public function test_real_proposed_migrations_preserve_legacy_records(): void
    {
        Schema::drop('pena_admin_users');
        $before = DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson();
        (require database_path('migrations/2026_10_05_000000_create_pena_admin_users_table.php'))->up();
        $this->installOrderTable();
        $this->assertTrue(Schema::hasColumns('pena_admin_users', ['id', 'name', 'email', 'password']));
        $this->assertTrue(Schema::hasColumns('pena_post_order', ['post_id', 'sort_order']));
        $this->assertSame($before, DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson());
        $this->assertDatabaseCount('pena_admin_users', 0);
        $this->assertDatabaseCount('pena_post_order', 0);
    }

    public function test_automatic_rollback_refuses_to_delete_admin_accounts(): void
    {
        $this->admin();
        try {
            (require database_path('migrations/2026_10_05_000000_create_pena_admin_users_table.php'))->down();
            $this->fail('Rollback must not delete administrative accounts.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('backup', $error->getMessage());
        }
        $this->assertDatabaseCount('pena_admin_users', 1);
    }

    public function test_automatic_rollback_refuses_to_delete_editorial_order(): void
    {
        $this->installOrderTable();
        DB::table('pena_post_order')->insert(['post_id' => 1, 'sort_order' => 1]);
        try {
            (require database_path('migrations/2026_10_05_000001_create_pena_post_order_table.php'))->down();
            $this->fail('Rollback must not delete editorial order.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('backup', $error->getMessage());
        }
        $this->assertDatabaseHas('pena_post_order', ['post_id' => 1, 'sort_order' => 1]);
    }
}
