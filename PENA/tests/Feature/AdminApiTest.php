<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EditorialAuthor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LegacyDatabaseTestCase;

class AdminApiTest extends LegacyDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->installOrderTable();
        DB::table('CATEGORIA_pena')->insert(['ID_CATEGORIA' => 1, 'NOME_CATEGORIA' => 'Teste']);
    }

    public function test_docs_spec_and_every_admin_operation_require_active_administrator(): void
    {
        foreach (['/docs', '/openapi/admin-v1.json', '/api/admin/v1/me', '/api/admin/v1/users'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/api/admin/v1/posts', [])->assertUnauthorized();

        $editor = AdminUser::create(['name' => 'Editora', 'email' => 'editor-api@example.com', 'password' => 'senha-de-teste-123']);
        $this->actingAs($editor);
        foreach (['/docs', '/openapi/admin-v1.json', '/api/admin/v1/me', '/api/admin/v1/posts'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->postJson('/api/admin/v1/posts', [])->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/docs')->assertOk()->assertSee('swagger-ui-bundle.js')->assertSee('csrf-token');
        foreach (['swagger-ui-bundle.js', 'swagger-ui-standalone-preset.js', 'swagger-ui.css', 'LICENSE'] as $asset) {
            $this->assertFileExists(public_path('vendor/swagger-ui/'.$asset));
        }
        $this->getJson('/openapi/admin-v1.json')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/admin/v1/me')->assertOk()->assertJsonPath('data.email', $admin->email)->assertDontSee($admin->password);
        $admin->is_active = false;
        $admin->save();
        $this->getJson('/openapi/admin-v1.json')->assertUnauthorized();
    }

    public function test_spec_documents_exactly_the_registered_admin_and_public_operations_without_secrets(): void
    {
        $this->actingAs($this->admin());
        $spec = $this->getJson('/openapi/admin-v1.json')->assertOk()->json();
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('/', $spec['servers'][0]['url']);
        $documented = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }
        $registered = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();
            if (! str_starts_with($uri, '/api/admin/v1/') && ! str_starts_with($uri, '/api/public/posts')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $registered[] = $method.' '.$uri;
                }
            }
        }
        sort($documented);
        sort($registered);
        $this->assertSame($registered, $documented);
        $encoded = json_encode($spec);
        $this->assertStringNotContainsString('senha-de-teste-123', $encoded);
        $this->assertStringNotContainsString('validator.swagger.io', $encoded);
        $this->assertStringNotContainsString('216.245.', $encoded);
    }

    public function test_api_reuses_user_and_author_services_without_serializing_passwords(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $user = $this->postJson('/api/admin/v1/users', [
            'name' => 'Pessoa de teste', 'email' => 'nova-api@example.com', 'password' => 'senha-segura-1234',
            'password_confirmation' => 'senha-segura-1234', 'role' => 'editor',
        ])->assertCreated()->assertDontSee('senha-segura-1234')->json('data');
        $this->assertArrayNotHasKey('password', $user);
        $this->getJson('/api/admin/v1/users/'.$user['id'])->assertOk()->assertJsonPath('data.role', 'editor');
        $this->patchJson('/api/admin/v1/users/'.$user['id'], [
            'name' => 'Nome alterado', 'email' => 'nova-api@example.com', 'role' => 'editor',
            'is_active' => true, 'expected_version' => $user['auth_version'],
        ])->assertOk()->assertJsonPath('data.name', 'Nome alterado');
        $author = $this->postJson('/api/admin/v1/authors', [
            'person_id' => 1, 'signature' => 'Autoria da API', 'slug' => 'autoria-da-api',
        ])->assertCreated()->json('data');
        $this->getJson('/api/admin/v1/authors/'.$author['id'])->assertOk()->assertJsonPath('data.slug', 'autoria-da-api');
        $this->patchJson('/api/admin/v1/authors/'.$author['id'], [
            'signature' => 'Autoria revisada', 'slug' => 'autoria-da-api', 'expected_version' => $author['version'],
        ])->assertOk()->assertJsonPath('data.signature', 'Autoria revisada');
        $this->patchJson('/api/admin/v1/authors/'.$author['id'].'/deactivate', ['expected_version' => 2])
            ->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_media_upload_validation_and_metadata_updates_use_private_storage(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/v1/media', ['alt_text' => 'Imagem'])->assertUnprocessable();
        $media = $this->post('/api/admin/v1/media', [
            'file' => UploadedFile::fake()->image('fixture.png', 32, 32), 'alt_text' => 'Imagem de teste',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('original_path', $media);
        $this->getJson('/api/admin/v1/media/'.$media['id'])->assertOk()->assertJsonPath('data.alt_text', 'Imagem de teste');
        $this->patchJson('/api/admin/v1/media/'.$media['id'], [
            'alt_text' => 'Novo texto', 'expected_version' => $media['version'],
        ])->assertOk()->assertJsonPath('data.alt_text', 'Novo texto');
        $this->patchJson('/api/admin/v1/media/'.$media['id'].'/deactivate')->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson('/api/admin/v1/media/'.$media['id'].'/activate')->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/admin/v1/media')->assertOk()->assertJsonStructure(['data' => ['uploaded', 'legacy']]);
    }

    public function test_post_api_defaults_to_write_denial_then_preserves_private_and_public_contracts(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['legacy_person_id' => 1])->save();
        $this->actingAs($admin);
        $payload = [
            'title' => 'Artigo de teste', 'slug' => 'artigo-de-teste',
            'html' => '<p>Seguro<script>alert(1)</script></p>', 'author_id' => EditorialAuthor::query()->firstOrFail()->id,
            'main_category_id' => 1, 'category_ids' => [1],
        ];
        $this->postJson('/api/admin/v1/posts', $payload)->assertStatus(503);
        config()->set('pena.editorial_writes_enabled', true);
        $created = $this->postJson('/api/admin/v1/posts', $payload)->assertCreated()->assertJsonPath('data.status', 'PO')->json('data');
        $this->assertStringNotContainsString('<script>', $created['html']);
        $id = $created['id'];
        $this->getJson('/api/public/posts/'.$id)->assertNotFound();
        $this->getJson('/api/admin/v1/posts?status=PO')->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
        $this->putJson('/api/admin/v1/posts/'.$id, $payload + ['expected_fingerprint' => $created['fingerprint']])
            ->assertOk();
        $this->putJson('/api/admin/v1/posts/'.$id, $payload + ['expected_fingerprint' => $created['fingerprint']])->assertStatus(409);
        $current = $this->getJson('/api/admin/v1/posts/'.$id)->assertOk()->json('data');
        $this->postJson('/api/admin/v1/posts/'.$id.'/publish', ['expected_fingerprint' => $current['fingerprint']])->assertOk()->assertJsonPath('data.status', 'PP');
        $this->getJson('/api/public/posts/'.$id)->assertOk();
        $current = $this->getJson('/api/admin/v1/posts/'.$id)->json('data');
        $this->postJson('/api/admin/v1/posts/'.$id.'/hide', ['expected_fingerprint' => $current['fingerprint']])->assertOk()->assertJsonPath('data.status', 'PO');
        $this->getJson('/api/public/posts/'.$id)->assertNotFound();
        $current = $this->getJson('/api/admin/v1/posts/'.$id)->json('data');
        $this->postJson('/api/admin/v1/posts/'.$id.'/delete', ['expected_fingerprint' => $current['fingerprint'], 'confirm_delete' => true])->assertOk()->assertJsonPath('data.status', 'PE');
    }

    public function test_order_conflict_and_csrf_gate(): void
    {
        $this->actingAs($this->admin());
        $list = $this->getJson('/api/admin/v1/posts/order')->assertOk()->json();
        $this->assertArrayNotHasKey('html', $list['data'][0]);
        $this->putJson('/api/admin/v1/posts/order', [
            'ids' => [1], 'expected_revision' => $list['meta']['revision'], 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $this->putJson('/api/admin/v1/posts/order', [
            'ids' => [1], 'expected_revision' => $list['meta']['revision'], 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
        $this->app->instance('env', 'local');
        $this->postJson('/api/admin/v1/posts', [])->assertStatus(419);
    }

    public function test_admin_api_does_not_grant_cross_origin_credentials_and_login_uses_same_accounts(): void
    {
        $admin = $this->admin();
        config()->set('cors.allowed_origins', ['https://site.example.com']);
        $this->withHeaders(['Origin' => 'https://site.example.com', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/admin/v1/posts')->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->withHeader('Origin', 'https://site.example.com')->actingAs($admin)
            ->getJson('/api/admin/v1/me')->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->post('/admin/logout')->assertRedirect();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/admin');
        $this->get('/docs')->assertOk();
        $admin->auth_version++;
        $admin->save();
        $this->getJson('/api/admin/v1/me')->assertUnauthorized();
    }

    public function test_direct_login_on_api_host_opens_docs_not_panel(): void
    {
        $admin = $this->admin();
        config()->set('pena.api_host', 'api.example.test');
        $this->post('https://api.example.test/admin/login', ['email' => $admin->email, 'password' => 'senha-de-teste-123'])
            ->assertRedirect('https://api.example.test/docs');
        $this->get('https://api.example.test/docs')->assertOk()
            ->assertSee('https://api.example.test/openapi/admin-v1.json')
            ->assertSee('https://api.example.test/vendor/swagger-ui/swagger-ui.css');
    }

    public function test_database_failure_does_not_expose_sql_or_private_values(): void
    {
        $this->actingAs($this->admin());
        DB::statement('DROP TABLE pena_admin_users');
        $this->getJson('/api/admin/v1/users')->assertStatus(503)
            ->assertHeader('X-Correlation-ID')->assertJsonStructure(['message', 'correlation_id'])
            ->assertDontSee('pena_admin_users')->assertDontSee('select *');
    }

    public function test_unexpected_order_storage_failure_returns_safe_503(): void
    {
        $this->actingAs($this->admin());
        $revision = $this->getJson('/api/admin/v1/posts/order')->assertOk()->json('meta.revision');
        DB::statement("CREATE TRIGGER synthetic_order_error BEFORE INSERT ON pena_post_order BEGIN SELECT RAISE(ABORT, 'synthetic private db detail'); END");
        $this->putJson('/api/admin/v1/posts/order', [
            'ids' => [1], 'expected_revision' => $revision, 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(503)->assertHeader('X-Correlation-ID')
            ->assertDontSee('synthetic private db detail')->assertDontSee('pena_post_order');
    }
}
