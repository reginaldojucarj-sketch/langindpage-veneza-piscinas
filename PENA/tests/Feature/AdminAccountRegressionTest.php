<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Services\AdminAccounts;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LegacyDatabaseTestCase;

class AdminAccountRegressionTest extends LegacyDatabaseTestCase
{
    private function target(): AdminUser
    {
        $user = AdminUser::create(['name' => 'Target', 'email' => 'target@example.invalid', 'password' => 'synthetic-password-123']);
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    private function form(AdminUser $user, array $changes = []): array
    {
        return array_replace($user->only(['name', 'email', 'role', 'is_active', 'legacy_person_id']), ['expected_version' => $user->auth_version], $changes);
    }

    public function test_creation_hashes_a_hash_shaped_password_as_literal_text(): void
    {
        $literal = Hash::make('x');
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Literal', 'email' => 'literal@example.invalid',
            'password' => $literal, 'password_confirmation' => $literal,
        ])->assertRedirect('/admin/users');
        $stored = AdminUser::where('email', 'literal@example.invalid')->sole()->password;
        $this->assertNotSame($literal, $stored);
        $this->assertTrue(Hash::check($literal, $stored));
        $this->assertFalse(Hash::check('x', $stored));
    }

    public function test_admin_reset_hashes_literal_input_and_never_accepts_inner_password(): void
    {
        $target = $this->target();
        $literal = Hash::make('x');
        $this->actingAs($this->admin())->post("/admin/users/{$target->id}/password", [
            'current_password' => 'senha-de-teste-123', 'password' => $literal, 'password_confirmation' => $literal,
        ])->assertRedirect();
        $this->assertTrue(Hash::check($literal, $target->fresh()->password));
        $this->assertFalse(Hash::check('x', $target->fresh()->password));
    }

    public function test_personal_password_change_hashes_literal_input(): void
    {
        $user = $this->admin();
        $literal = Hash::make('x');
        $this->actingAs($user)->post('/admin/account/password', [
            'current_password' => 'senha-de-teste-123', 'password' => $literal, 'password_confirmation' => $literal,
        ])->assertRedirect();
        $this->assertTrue(Hash::check($literal, $user->fresh()->password));
        $this->assertFalse(Hash::check('x', $user->fresh()->password));
    }

    public function test_stale_form_cannot_reactivate_or_restore_admin_role(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $oldForm = $this->form($target, ['name' => 'Name from stale form']);
        $this->actingAs($admin);
        $updated = app(AdminAccounts::class)->update($admin, $target->id, $this->form($target, ['role' => 'editor', 'is_active' => false]));
        $url = "/admin/users/{$target->id}/edit";
        $this->from($url)->patch("/admin/users/{$target->id}", $oldForm)
            ->assertRedirect($url)->assertSessionHasErrors('expected_version');
        // A validation redirect must not replace the stale token with a fresh one.
        $this->get($url)->assertOk()->assertSee('name="expected_version" value="1"', false);
        $this->patchJson("/admin/users/{$target->id}", $oldForm)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertSame('editor', $target->fresh()->role);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame('Target', $target->fresh()->name);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertDatabaseCount('pena_admin_audit', 1);
        // Explicitly reloading and submitting current data succeeds without restoring access.
        $this->patch("/admin/users/{$target->id}", $this->form($updated, ['name' => 'Reviewed name']))->assertRedirect('/admin/users');
        $this->assertSame('Reviewed name', $target->fresh()->name);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(3, $target->fresh()->auth_version);
    }

    public function test_target_version_is_required_validated_and_never_assigned(): void
    {
        $target = $this->target();
        $this->actingAs($this->admin());
        foreach ([null, [], 'wrong', 0, -1, 1.5, 999, '999999999999999999999'] as $version) {
            $this->patchJson("/admin/users/{$target->id}", $this->form($target, ['expected_version' => $version]))
                ->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        }
        $missing = $this->form($target);
        unset($missing['expected_version']);
        $this->patchJson("/admin/users/{$target->id}", $missing)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertDatabaseCount('pena_admin_audit', 0);
        $this->patch("/admin/users/{$target->id}", $this->form($target, ['expected_version' => '1', 'auth_version' => 50, 'name' => 'Valid name']))->assertRedirect();
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertArrayNotHasKey('expected_version', $target->fresh()->getAttributes());
    }

    public function test_password_reset_invalidates_an_older_account_edit_form(): void
    {
        $admin = $this->admin();
        $target = $this->target();
        $oldForm = $this->form($target, ['name' => 'Stale']);
        $this->actingAs($admin)->post("/admin/users/{$target->id}/password", [
            'current_password' => 'senha-de-teste-123', 'password' => 'new-synthetic-password', 'password_confirmation' => 'new-synthetic-password',
        ])->assertRedirect();
        $this->patchJson("/admin/users/{$target->id}", $oldForm)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertSame('Target', $target->fresh()->name);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->assertDatabaseCount('pena_admin_audit', 1);
    }
}
