<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LegacyDatabaseTestCase;

class AdminAndPublicPostsTest extends LegacyDatabaseTestCase
{
    public function test_admin_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/posts/order')->assertRedirect('/admin/login');
        $this->get('/admin/users')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Acessar o painel');
    }

    public function test_public_api_excludes_unpublished_articles(): void
    {
        $this->getJson('/api/public/posts')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1);
        $this->getJson('/api/public/posts/1')->assertOk()->assertJsonPath('data.title', 'Publicado');
        $this->getJson('/api/public/posts/2')->assertNotFound();
    }

    public function test_admin_can_login_and_logout_with_a_local_test_account(): void
    {
        AdminUser::create(['name' => 'Teste', 'email' => 'teste@example.com', 'password' => 'senha-de-teste-123']);

        $this->post('/admin/login', ['email' => 'teste@example.com', 'password' => 'senha-errada'])
            ->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'teste@example.com', 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Painel administrativo');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_public_api_cors_uses_an_exact_origin_allowlist(): void
    {
        config()->set('cors.allowed_origins', ['https://site.example.com']);

        $this->withHeader('Origin', 'https://site.example.com')->getJson('/api/public/posts')
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example.com');
        $this->withHeader('Origin', 'https://other.example.com')->getJson('/api/public/posts')
            ->assertHeader('Access-Control-Allow-Origin', 'https://site.example.com');
    }

    public function test_admin_creation_refuses_to_run_without_a_backup(): void
    {
        $this->artisan('pena:create-admin')->assertExitCode(1);
        $this->assertDatabaseCount('pena_admin_users', 0);
    }

    public function test_login_explains_when_database_is_not_configured(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', '');

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'irrelevante'])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_change_published_post_order_in_isolated_database(): void
    {
        $this->installOrderTable();
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Outro publicado', 'STATUS_POST' => 'PP']);
        $user = $this->admin();

        $this->actingAs($user)->get('/admin/posts/order')->assertOk()->assertSee('Ordem dos artigos');
        $base = ['expected_revision' => 1, 'idempotency_key' => (string) Str::uuid()];
        $this->post('/admin/posts/order', $base + ['ids' => [1, 2, 3]])->assertStatus(409);
        $this->post('/admin/posts/order', ['expected_revision' => 1, 'idempotency_key' => (string) Str::uuid(), 'ids' => [1, 3]])->assertRedirect('/admin/posts/order');
        $this->getJson('/api/public/posts')->assertJsonPath('data.0.id', 1)->assertJsonPath('data.1.id', 3);
        $this->post('/admin/posts/order', ['expected_revision' => 2, 'idempotency_key' => (string) Str::uuid(), 'ids' => [3, 1]])->assertRedirect('/admin/posts/order');
        $this->getJson('/api/public/posts')->assertJsonPath('data.0.id', 3)->assertJsonPath('data.1.id', 1);
        $this->assertDatabaseHas('pena_post_order', ['post_id' => 3, 'sort_order' => 1]);
    }

    public function test_authenticated_admin_can_create_another_user(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Usuários administrativos');

        $this->post('/admin/users', [
            'name' => 'Outro Gestor',
            'email' => '  NOVO@example.com  ',
            'password' => 'uma-senha-forte-123',
            'password_confirmation' => 'uma-senha-forte-123',
        ])->assertRedirect('/admin/users');

        $this->assertDatabaseHas('pena_admin_users', ['name' => 'Outro Gestor', 'email' => 'novo@example.com']);
        $this->assertNotSame('uma-senha-forte-123', AdminUser::where('email', 'novo@example.com')->first()->password);
        $this->post('/admin/users', [
            'name' => 'Duplicado',
            'email' => 'novo@example.com',
            'password' => 'uma-senha-forte-123',
            'password_confirmation' => 'uma-senha-forte-123',
        ])->assertSessionHasErrors('email');
    }
}
