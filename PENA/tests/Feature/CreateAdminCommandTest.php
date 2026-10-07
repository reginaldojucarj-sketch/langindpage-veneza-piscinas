<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyDatabaseTestCase;

class CreateAdminCommandTest extends LegacyDatabaseTestCase
{
    private string $backup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backup = tempnam(sys_get_temp_dir(), 'pena-test-');
        // Synthetic fixture: proves the hash guard, NOT backup restorability.
        file_put_contents($this->backup, '-- synthetic backup fixture');
    }

    protected function tearDown(): void
    {
        if (isset($this->backup) && is_file($this->backup)) {
            unlink($this->backup);
        }
        parent::tearDown();
    }

    private function backupOptions(): array
    {
        return ['--backup' => $this->backup, '--sha256' => hash_file('sha256', $this->backup)];
    }

    public function test_incorrect_hash_and_empty_file_are_rejected_before_writes(): void
    {
        $this->artisan('pena:create-admin', ['--backup' => $this->backup, '--sha256' => str_repeat('0', 64)])->assertFailed();
        file_put_contents($this->backup, '');
        clearstatcache(true, $this->backup);
        $this->artisan('pena:create-admin', $this->backupOptions())->assertFailed();
        $this->assertDatabaseCount('pena_admin_users', 0);
    }

    public function test_missing_admin_table_is_reported_without_running_migrations(): void
    {
        Schema::drop('pena_admin_users');
        $this->artisan('pena:create-admin', $this->backupOptions())->assertFailed();
        $this->assertFalse(Schema::hasTable('pena_admin_users'));
    }

    public function test_valid_input_creates_only_a_hashed_account_in_the_test_database(): void
    {
        $this->artisan('pena:create-admin', $this->backupOptions())
            ->expectsQuestion('Nome do administrador', ' Admin Teste ')
            ->expectsQuestion('E-mail', ' ADMIN@EXAMPLE.COM ')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'senha-local-teste-123')
            ->expectsQuestion('Confirme a senha', 'senha-local-teste-123')
            ->assertSuccessful();
        $user = AdminUser::sole();
        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame('Admin Teste', $user->name);
        $this->assertTrue(Hash::check('senha-local-teste-123', $user->password));
        $this->assertTrue($user->isAdministrator());
        $this->assertDatabaseHas('pena_admin_audit', ['actor_id' => null, 'target_id' => $user->id, 'action' => 'account.created_cli']);
    }

    public function test_password_mismatch_is_rejected(): void
    {
        $this->artisan('pena:create-admin', $this->backupOptions())
            ->expectsQuestion('Nome do administrador', 'Admin')
            ->expectsQuestion('E-mail', 'admin@example.com')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'senha-local-teste-123')
            ->expectsQuestion('Confirme a senha', 'diferente')
            ->assertFailed();
        $this->assertDatabaseCount('pena_admin_users', 0);
    }

    public function test_cli_treats_hash_shaped_password_as_literal_text(): void
    {
        $literal = Hash::make('x');
        $this->artisan('pena:create-admin', $this->backupOptions())
            ->expectsQuestion('Nome do administrador', 'Synthetic Admin')
            ->expectsQuestion('E-mail', 'admin@example.invalid')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', $literal)
            ->expectsQuestion('Confirme a senha', $literal)
            ->assertSuccessful();
        $this->assertTrue(Hash::check($literal, AdminUser::sole()->password));
        $this->assertFalse(Hash::check('x', AdminUser::sole()->password));
    }

    public function test_existing_account_is_not_overwritten(): void
    {
        $user = $this->admin();
        $hash = $user->password;
        $this->artisan('pena:create-admin', $this->backupOptions())
            ->expectsQuestion('Nome do administrador', 'Outra pessoa')
            ->expectsQuestion('E-mail', 'teste@example.com')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'outra-senha-teste-123')
            ->expectsQuestion('Confirme a senha', 'outra-senha-teste-123')
            ->assertFailed();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('pena_admin_users', 1);
    }
}
