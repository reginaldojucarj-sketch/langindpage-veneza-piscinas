<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\Support\LegacyDatabaseTestCase;

class AdminSecurityTest extends LegacyDatabaseTestCase
{
    public function test_guests_cannot_perform_administrative_writes(): void
    {
        foreach (['/admin/users', '/admin/posts/order', '/admin/logout'] as $route) {
            $this->post($route)->assertRedirect('/admin/login');
        }
        $this->assertDatabaseCount('pena_admin_users', 0);
    }

    public function test_login_normalizes_email_and_never_flashes_password(): void
    {
        $this->admin();
        $this->post('/admin/login', ['email' => ' TESTE@EXAMPLE.COM ', 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->get('/admin/login')->assertRedirect('/admin');
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'teste@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_login_throttles_repeated_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', ['email' => 'absent@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', ['email' => 'absent@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_csrf_is_required_even_for_an_authenticated_user(): void
    {
        $this->actingAs($this->admin());
        // Laravel bypasses CSRF in testing; only switch that environment check,
        // after the in-memory DB safety guard has executed.
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'expected-token']);
        foreach (['/admin/users', '/admin/posts/order', '/admin/logout'] as $route) {
            $this->post($route)->assertStatus(419);
        }
        $this->assertDatabaseCount('pena_admin_users', 1);
    }

    public function test_invalid_user_payloads_do_not_create_accounts(): void
    {
        $this->actingAs($this->admin());
        $valid = ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'senha-de-teste-123', 'password_confirmation' => 'senha-de-teste-123'];
        foreach ([
            ['name' => ' '], ['email' => 'invalid'], ['email' => ['array@example.com']],
            ['password' => 'short'], ['password_confirmation' => 'different'],
            ['email' => 'TESTE@example.com'],
        ] as $invalid) {
            $this->postJson('/admin/users', array_replace($valid, $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('pena_admin_users', 1);
    }

    public function test_password_is_hashed_and_credentials_are_not_serialized(): void
    {
        $user = $this->admin();
        $this->assertTrue(Hash::check('senha-de-teste-123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->actingAs($user)->get('/admin/users')->assertDontSee($user->password);
    }

    public function test_user_names_are_escaped_in_the_panel(): void
    {
        $user = $this->admin();
        $user->update(['name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get('/admin/users')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
