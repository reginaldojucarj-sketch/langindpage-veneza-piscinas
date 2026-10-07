<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyDatabaseTestCase;

class AdditiveMigrationsTest extends LegacyDatabaseTestCase
{
    public function test_access_migration_preserves_existing_admins_and_legacy_data(): void
    {
        Schema::drop('pena_admin_audit');
        Schema::drop('pena_admin_access_lock');
        Schema::drop('pena_admin_users');
        (require database_path('migrations/2026_10_05_000000_create_pena_admin_users_table.php'))->up();
        DB::table('pena_admin_users')->insert(['id' => 21, 'name' => 'Anterior', 'email' => 'anterior@example.com', 'password' => 'synthetic-placeholder']);
        $before = DB::table('POST_pena')->get()->toJson();
        (require database_path('migrations/2026_10_05_000002_add_admin_access_controls.php'))->up();
        $this->assertDatabaseHas('pena_admin_users', ['id' => 21, 'role' => 'admin', 'is_active' => true, 'auth_version' => 1, 'password' => 'synthetic-placeholder', 'legacy_person_id' => null]);
        $this->assertSame($before, DB::table('POST_pena')->get()->toJson());
        $this->assertDatabaseHas('pena_admin_access_lock', ['id' => 1]);
        $this->assertDatabaseCount('pena_admin_audit', 0);
    }

    public function test_access_rollback_requires_manual_review(): void
    {
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_05_000002_add_admin_access_controls.php'))->down();
    }

    public function test_real_proposed_migrations_preserve_legacy_records(): void
    {
        Schema::drop('pena_admin_users');
        $before = DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson();
        (require database_path('migrations/2026_10_05_000000_create_pena_admin_users_table.php'))->up();
        $this->installOrderTable();
        $this->assertTrue(Schema::hasColumns('pena_admin_users', ['id', 'name', 'email', 'password']));
        $this->assertTrue(Schema::hasColumns('pena_post_order', ['post_id', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('pena_editorial_state', ['id', 'revision', 'published_digest']));
        $this->assertTrue(Schema::hasColumns('pena_editorial_audit', ['correlation_id', 'actor_id', 'action', 'outcome', 'payload_hash']));
        $this->assertTrue(Schema::hasColumns('pena_authors', ['legacy_author_id', 'person_id', 'signature', 'slug', 'is_active', 'version']));
        $this->assertTrue(Schema::hasColumns('pena_media', ['sha256', 'original_path', 'derivative_path', 'alt_text', 'uploaded_by']));
        $this->assertDatabaseHas('pena_authors', ['legacy_author_id' => 9, 'signature' => 'Autoria sintética']);
        $this->assertDatabaseHas('pena_post_author_assignments', ['post_id' => 1, 'author_signature_snapshot' => 'Autoria sintética', 'source' => 'legacy']);
        $this->assertDatabaseMissing('pena_post_author_assignments', ['post_id' => 2]);
        $this->assertTrue(Schema::hasTable('pena_post_media_assignments'));
        $this->assertTrue(Schema::hasTable('pena_content_audit'));
        $this->assertSame($before, DB::table('POST_pena')->orderBy('ID_POST')->get()->toJson());
        $this->assertDatabaseCount('pena_admin_users', 0);
        $this->assertDatabaseCount('pena_post_order', 0);
        $this->assertDatabaseCount('pena_editorial_audit', 0);
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
