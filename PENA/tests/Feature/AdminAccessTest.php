<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Services\AdminAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\LegacyDatabaseTestCase;

class AdminAccessTest extends LegacyDatabaseTestCase
{
    private function editor(): AdminUser
    {
        return AdminUser::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'senha-de-teste-123']);
    }

    private function data(AdminUser $user, array $changes = []): array
    {
        return array_replace($user->only(['name', 'email', 'role', 'is_active', 'legacy_person_id']), ['expected_version' => $user->auth_version], $changes);
    }

    public function test_role_and_active_permission_matrix(): void
    {
        $user = $this->admin();
        foreach (['admin', 'editor', 'unknown'] as $role) {
            foreach ([true, false] as $active) {
                $user->forceFill(['role' => $role, 'is_active' => $active]);
                foreach (['manage-users', 'edit-content', 'publish-content', 'delete-content', 'order-posts'] as $ability) {
                    $expected = $active && ($role === 'admin' || ($role === 'editor' && $ability === 'edit-content'));
                    $this->assertSame($expected, Gate::forUser($user)->allows($ability), "$role/$active/$ability");
                }
            }
        }
    }

    public function test_editor_cannot_use_any_user_mutation_or_order_route(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $this->actingAs($editor)->get('/admin')->assertOk()->assertDontSee('/admin/users', false);
        foreach (['/admin/users', "/admin/users/{$admin->id}/edit", '/admin/posts/order'] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/admin/users', "/admin/users/{$admin->id}/password", '/admin/posts/order'] as $url) {
            $this->postJson($url, ['role' => 'admin'])->assertForbidden();
        }
        $this->patchJson("/admin/users/{$editor->id}", $this->data($editor, ['role' => 'admin']))->assertForbidden();
        $this->assertSame('editor', $editor->fresh()->role);
        $this->assertDatabaseCount('pena_admin_audit', 0);
    }

    public function test_guests_receive_401_for_json_and_no_public_registration(): void
    {
        $this->postJson('/admin/users')->assertUnauthorized();
        $this->patchJson('/admin/users/1')->assertUnauthorized();
        $this->postJson('/admin/users/1/password')->assertUnauthorized();
        $this->postJson('/admin/account/password')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
    }

    public function test_inactive_account_cannot_login_or_reuse_a_session(): void
    {
        $user = $this->admin();
        $user->forceFill(['is_active' => false])->save();
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'senha-de-teste-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->getJson('/admin')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_last_admin_cannot_be_deactivated_or_demoted(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        foreach ([['is_active' => false], ['role' => 'editor']] as $change) {
            $this->patchJson("/admin/users/{$user->id}", $this->data($user, $change))->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertTrue($user->fresh()->isAdministrator());
        $this->assertDatabaseCount('pena_admin_audit', 0);
    }

    public function test_self_deactivation_with_another_admin_revokes_the_session(): void
    {
        $user = $this->admin();
        $this->editor()->forceFill(['role' => 'admin'])->save();
        $this->actingAs($user)->patch("/admin/users/{$user->id}", $this->data($user, ['is_active' => false]))->assertRedirect('/admin/users');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertSame(2, $user->fresh()->auth_version);
    }

    public function test_demotion_revokes_old_session_and_new_login_has_editor_permissions(): void
    {
        $admin = $this->admin();
        $other = $this->editor();
        $other->forceFill(['role' => 'admin', 'remember_token' => 'synthetic-token'])->save();
        $this->actingAs($other);
        app(AdminAccounts::class)->update($admin, $other->id, $this->data($other, ['role' => 'editor']));
        $this->assertNull($other->fresh()->remember_token);
        $this->getJson('/admin/users')->assertUnauthorized();
        $this->assertNotSame('synthetic-token', $other->fresh()->remember_token);
        $this->post('/admin/login', ['email' => $other->email, 'password' => 'senha-de-teste-123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_missing_session_version_is_rejected(): void
    {
        $this->actingAs($this->admin());
        session()->forget('pena_auth_version');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_reactivation_does_not_resurrect_old_sessions(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $this->actingAs($editor);
        $service = app(AdminAccounts::class);
        $inactive = $service->update($admin, $editor->id, $this->data($editor, ['is_active' => false]));
        $service->update($admin, $editor->id, $this->data($inactive, ['is_active' => true]));
        $this->assertSame(3, $editor->fresh()->auth_version);
        $this->getJson('/admin')->assertUnauthorized();
    }

    public function test_service_rechecks_actor_after_lock_instead_of_trusting_stale_role(): void
    {
        $admin = $this->admin();
        $other = $this->editor();
        $other->forceFill(['role' => 'admin'])->save();
        $service = app(AdminAccounts::class);
        $service->update($admin, $other->id, $this->data($other, ['role' => 'editor']));
        try {
            $service->update($other, $admin->id, $this->data($admin, ['is_active' => false]));
            $this->fail('Stale administrator must not mutate accounts.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    public function test_admin_edits_accounts_with_explicit_optional_person_mapping(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        DB::table('PESSOA_pena')->insert(['ID_PESSOA' => 37, 'NOME_PESSOA' => 'Pessoa fictícia', 'SOBRENOME_PESSOA' => 'Teste']);
        $this->actingAs($admin)->get("/admin/users/{$editor->id}/edit")->assertOk()->assertSee('ID da pessoa legada');
        $this->patch("/admin/users/{$editor->id}", $this->data($editor, ['name' => 'Novo nome', 'email' => ' NOVO@EXAMPLE.COM ', 'legacy_person_id' => 37]))->assertRedirect('/admin/users');
        $this->assertDatabaseHas('pena_admin_users', ['id' => $editor->id, 'name' => 'Novo nome', 'email' => 'novo@example.com', 'legacy_person_id' => 37]);
        $this->patchJson("/admin/users/{$admin->id}", $this->data($admin, ['legacy_person_id' => 37]))->assertUnprocessable()->assertJsonValidationErrors('legacy_person_id');
        $this->patchJson("/admin/users/{$editor->id}", $this->data($editor, ['legacy_person_id' => 999]))->assertUnprocessable();
        $this->assertDatabaseCount('PESSOA_pena', 2);
    }

    public function test_unexpected_fields_and_mass_assignment_cannot_change_privileges_or_password(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $before = $editor->password;
        $editor->fill(['role' => 'admin', 'is_active' => false, 'auth_version' => 99, 'legacy_person_id' => 42]);
        $this->assertSame('editor', $editor->role);
        $this->assertTrue($editor->is_active);
        $this->assertSame(1, $editor->auth_version);
        $this->actingAs($admin)->patch("/admin/users/{$editor->id}", $this->data($editor, ['password' => 'injected-password', 'auth_version' => 100, 'id' => $admin->id, 'remember_token' => 'injected']))->assertRedirect();
        $this->assertSame($before, $editor->fresh()->password);
        $this->assertSame(1, $editor->fresh()->auth_version);
        $this->assertNull($editor->fresh()->remember_token);
    }

    public function test_invalid_update_payloads_and_case_duplicate_are_rejected(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $this->actingAs($admin);
        foreach ([['email' => 'TESTE@EXAMPLE.COM'], ['email' => []], ['name' => []], ['role' => []], ['role' => 'superadmin'], ['is_active' => 'yes'], ['legacy_person_id' => []]] as $invalid) {
            $this->patchJson("/admin/users/{$editor->id}", $this->data($editor, $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('pena_admin_audit', 0);
    }

    public function test_password_change_requires_current_password_and_revokes_all_sessions(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->get('/admin/account/password')->assertOk();
        $payload = ['current_password' => 'wrong', 'password' => 'new-private-pass-123', 'password_confirmation' => 'new-private-pass-123'];
        $this->post('/admin/account/password', $payload)->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password');
        $payload['current_password'] = 'senha-de-teste-123';
        $this->post('/admin/account/password', $payload)->assertRedirect('/admin');
        $this->assertTrue(Hash::check($payload['password'], $editor->fresh()->password));
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->assertDatabaseHas('pena_admin_audit', ['actor_id' => $editor->id, 'target_id' => $editor->id, 'action' => 'password.changed']);
    }

    public function test_admin_password_reset_is_audited_without_values_and_old_session_expires(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        $payload = ['current_password' => 'senha-de-teste-123', 'password' => 'new-private-pass-123', 'password_confirmation' => 'new-private-pass-123'];
        $this->actingAs($admin)->post("/admin/users/{$editor->id}/password", $payload)->assertRedirect("/admin/users/{$editor->id}/edit");
        $this->assertTrue(Hash::check($payload['password'], $editor->fresh()->password));
        $audit = DB::table('pena_admin_audit')->first();
        $this->assertSame('password.reset_admin', $audit->action);
        $this->assertSame('["password"]', $audit->changed_fields);
        $this->assertStringNotContainsString($payload['password'], json_encode($audit));
        $this->assertStringNotContainsString($editor->fresh()->password, json_encode($audit));
        $this->assertArrayNotHasKey('auth_version', $editor->fresh()->toArray());
        $this->actingAs($editor)->getJson('/admin')->assertUnauthorized();
    }

    public function test_password_limits_and_confirmation_apply_on_reset(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        foreach ([['short', 'short'], [str_repeat('é', 37), str_repeat('é', 37)], ["123456789012\0", "123456789012\0"], ['long-enough-password', 'mismatch']] as [$password, $confirmation]) {
            $this->postJson('/admin/account/password', ['current_password' => 'senha-de-teste-123', 'password' => $password, 'password_confirmation' => $confirmation])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertSame(1, $user->fresh()->auth_version);
    }

    public function test_sensitive_db_errors_are_redacted_and_failed_audit_rolls_back(): void
    {
        $admin = $this->admin();
        $editor = $this->editor();
        DB::statement('DROP TABLE pena_admin_audit');
        Log::shouldReceive('error')->once()->with('Falha no banco administrativo.', \Mockery::on(fn ($context) => array_keys($context) === ['sqlstate']));
        $this->actingAs($admin)->post("/admin/users/{$editor->id}/password", ['current_password' => 'senha-de-teste-123', 'password' => 'new-private-pass-123', 'password_confirmation' => 'new-private-pass-123'])->assertStatus(503)->assertDontSee('new-private-pass-123')->assertDontSee($editor->password);
        $this->assertSame($editor->password, $editor->fresh()->password);
        $this->assertSame(1, $editor->fresh()->auth_version);
    }

    public function test_new_mutations_require_csrf(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $this->app->instance('env', 'local');
        $this->patch("/admin/users/{$user->id}")->assertStatus(419);
        $this->post("/admin/users/{$user->id}/password")->assertStatus(419);
        $this->post('/admin/account/password')->assertStatus(419);
    }

    public function test_password_requests_are_throttled(): void
    {
        $this->actingAs($this->admin());
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/account/password')->assertUnprocessable();
        }
        $this->postJson('/admin/account/password')->assertStatus(429);
    }

    public function test_login_rotates_session_id_and_logout_removes_stamp(): void
    {
        $user = $this->admin();
        $this->withSession(['example' => 'value']);
        $id = session()->getId();
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'senha-de-teste-123'])->assertRedirect('/admin')->assertSessionHas('pena_auth_version', 1);
        $this->assertNotSame($id, session()->getId());
        $this->get('/admin')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/admin/logout')->assertSessionMissing('pena_auth_version');
        $this->assertGuest();
    }
}
