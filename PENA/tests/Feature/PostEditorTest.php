<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EditorialAuthor;
use App\Services\ContentAudit;
use App\Services\PostEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\LegacyDatabaseTestCase;

class PostEditorTest extends LegacyDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->installOrderTable();
        DB::table('CATEGORIA_pena')->insert([
            ['ID_CATEGORIA' => 1, 'NOME_CATEGORIA' => 'Tratamento'],
            ['ID_CATEGORIA' => 2, 'NOME_CATEGORIA' => 'Equipamentos'],
        ]);
        config()->set('pena.editorial_writes_enabled', true);
    }

    private function linkedAdmin(): AdminUser
    {
        $admin = $this->admin();
        $admin->forceFill(['legacy_person_id' => 1])->save();

        return $admin;
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Água de piscina limpa', 'slug' => '',
            'html' => '<p>Texto <strong>seguro</strong><script>alert(1)</script></p>',
            'snippet' => 'Resumo', 'description' => 'Descrição', 'keywords' => 'piscina',
            'cover_url' => 'https://example.com/capa.jpg',
            'author_id' => EditorialAuthor::query()->firstOrFail()->id,
            'main_category_id' => 1, 'category_ids' => [1, 2], 'media_id' => '',
        ], $overrides);
    }

    private function created(AdminUser $admin): int
    {
        $this->actingAs($admin)->post('/admin/posts', $this->input())->assertRedirect();

        return (int) DB::table('POST_pena')->where('LINK_POST', 'agua-de-piscina-limpa')->value('ID_POST');
    }

    public function test_writes_are_off_by_default_and_guest_cannot_open_private_preview(): void
    {
        $this->get('/admin/posts/2/preview')->assertRedirect('/admin/login');
        $admin = $this->linkedAdmin();
        config()->set('pena.editorial_writes_enabled', false);
        $this->actingAs($admin)->get('/admin/posts/create')->assertOk()->assertSee('escrita está bloqueada');
        $this->actingAs($admin)->post('/admin/posts', $this->input())->assertStatus(503);
        $this->assertDatabaseCount('POST_pena', 2);
    }

    public function test_list_filters_and_edit_form_render_without_exposing_hidden_posts_publicly(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $this->actingAs($admin)->get('/admin/posts?q=Água&status=PO')->assertOk()
            ->assertSee('Água de piscina limpa')->assertSee('Novo artigo');
        $this->actingAs($admin)->get('/admin/posts/'.$id.'/edit')->assertOk()
            ->assertSee('Conteúdo HTML')->assertSee('expected_fingerprint');
        $this->getJson('/api/public/posts/'.$id)->assertNotFound();
    }

    public function test_admin_post_search_treats_wildcards_literally(): void
    {
        DB::table('POST_pena')->insert(['ID_POST' => 3, 'TITULO_POST' => 'Oferta 50%', 'STATUS_POST' => 'PO']);
        $this->assertSame(1, app(PostEditor::class)->listing('%', null)->total());
        $this->assertSame(0, app(PostEditor::class)->listing('_', null)->total());
    }

    public function test_admin_post_pagination_uses_portuguese_labels_and_preserves_filters(): void
    {
        foreach (range(3, 28) as $id) {
            DB::table('POST_pena')->insert([
                'ID_POST' => $id, 'TITULO_POST' => 'Lote '.$id, 'STATUS_POST' => 'PO',
            ]);
        }

        $admin = $this->linkedAdmin();
        $this->actingAs($admin)->get('/admin/posts?q=Lote&status=PO')->assertOk()
            ->assertSee('Página 1 de 2')->assertSee('Próxima')
            ->assertSee('aria-current="page"', false)
            ->assertSee('q=Lote')->assertSee('status=PO');
        $this->actingAs($admin)->get('/admin/posts?q=Lote&status=PO&page=2')->assertOk()
            ->assertSee('Página 2 de 2')->assertSee('Anterior')
            ->assertSee('Lote 3')->assertDontSee('Lote 28');
    }

    public function test_creator_requires_explicit_legacy_person_mapping(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/posts', $this->input())->assertSessionHasErrors('person');
        $this->assertDatabaseCount('POST_pena', 2);
    }

    public function test_create_preview_publish_hide_and_delete_preserve_record_and_public_visibility(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $this->assertGreaterThan(2, $id);
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'STATUS_POST' => 'PO', 'LINK_POST' => 'agua-de-piscina-limpa']);
        $this->assertDatabaseHas('pena_post_author_assignments', ['post_id' => $id]);
        $this->assertSame(2, DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->count());
        $this->getJson('/api/public/posts/'.$id)->assertNotFound();
        $this->actingAs($admin)->get('/admin/posts/'.$id.'/preview')->assertOk()
            ->assertSee('Texto <strong>seguro</strong>', false)->assertDontSee('<script>', false)
            ->assertHeader('Cache-Control', 'no-store, private');

        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])->assertRedirect();
        $this->getJson('/api/public/posts/'.$id)->assertOk()->assertJsonPath('data.slug', 'agua-de-piscina-limpa')
            ->assertHeader('Cache-Control', 'no-store, private');
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/hide', ['expected_fingerprint' => $token])->assertRedirect();
        $this->getJson('/api/public/posts/'.$id)->assertNotFound();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/delete', ['expected_fingerprint' => $token, 'confirm_delete' => '1'])->assertRedirect();
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'STATUS_POST' => 'PE', 'TITULO_POST' => 'Água de piscina limpa']);
        $this->assertDatabaseCount('pena_post_author_assignments', 2);
        $this->assertSame(2, DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->count());
    }

    public function test_stale_edit_returns_409_and_keeps_first_edit_and_submitted_draft(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $first = $this->input(['slug' => 'agua-de-piscina-limpa', 'title' => 'Primeira edição', 'expected_fingerprint' => $token]);
        $this->actingAs($admin)->put('/admin/posts/'.$id, $first)->assertRedirect();
        $second = $this->input(['slug' => 'agua-de-piscina-limpa', 'title' => 'Rascunho obsoleto', 'expected_fingerprint' => $token]);
        $this->actingAs($admin)->put('/admin/posts/'.$id, $second)->assertStatus(409)->assertSee('Rascunho obsoleto');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'TITULO_POST' => 'Primeira edição']);
    }

    public function test_slug_can_change_in_a_new_draft_but_is_frozen_after_publication(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->put('/admin/posts/'.$id, $this->input([
            'slug' => 'novo-slug-de-rascunho', 'expected_fingerprint' => $token,
        ]))->assertRedirect();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])->assertRedirect();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->putJson('/admin/posts/'.$id, $this->input([
            'slug' => 'outro-slug', 'expected_fingerprint' => $token,
        ]))->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'LINK_POST' => 'novo-slug-de-rascunho']);
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/hide', ['expected_fingerprint' => $token])->assertRedirect();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->putJson('/admin/posts/'.$id, $this->input([
            'slug' => 'outro-slug', 'expected_fingerprint' => $token,
        ]))->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_managed_cover_and_author_snapshots_survive_unrelated_edits(): void
    {
        $admin = $this->linkedAdmin();
        $mediaId = (string) Str::uuid();
        DB::table('pena_media')->insert([
            'id' => $mediaId, 'sha256' => hash('sha256', 'synthetic post cover'),
            'original_name' => 'capa.jpg', 'source_mime' => 'image/jpeg', 'public_mime' => 'image/jpeg',
            'original_bytes' => 100, 'width' => 120, 'height' => 90, 'alt_text' => 'Capa original',
            'original_path' => $mediaId.'.jpg', 'derivative_path' => $mediaId.'.jpg',
            'is_active' => true, 'uploaded_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->post('/admin/posts', $this->input(['media_id' => $mediaId]))->assertRedirect();
        $id = (int) DB::table('POST_pena')->where('LINK_POST', 'agua-de-piscina-limpa')->value('ID_POST');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'URL_IMAGEM_POST' => route('media.public', $mediaId)]);
        $this->assertDatabaseHas('pena_post_media_assignments', ['post_id' => $id, 'alt_text_snapshot' => 'Capa original']);
        $oldAuthor = DB::table('pena_post_author_assignments')->where('post_id', $id)->value('author_signature_snapshot');
        DB::table('pena_media')->where('id', $mediaId)->update(['alt_text' => 'Novo texto alternativo']);
        DB::table('pena_authors')->where('id', EditorialAuthor::query()->firstOrFail()->id)->update(['signature' => 'Novo nome']);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->put('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'media_id' => $mediaId,
            'title' => 'Texto atualizado', 'expected_fingerprint' => $token,
        ]))->assertRedirect();
        $this->assertDatabaseHas('pena_post_author_assignments', ['post_id' => $id, 'author_signature_snapshot' => $oldAuthor]);
        $this->assertDatabaseHas('pena_post_media_assignments', ['post_id' => $id, 'alt_text_snapshot' => 'Capa original']);
    }

    public function test_editor_can_edit_hidden_but_cannot_publish_or_change_published_article(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $editor = AdminUser::create(['name' => 'Editora', 'email' => 'editora-post@example.com', 'password' => 'senha-de-teste-123']);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($editor)->put('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'title' => 'Revisão editorial', 'expected_fingerprint' => $token,
        ]))->assertRedirect();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($editor)->post('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])->assertForbidden();
        $this->actingAs($admin)->post('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])->assertRedirect();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($editor)->putJson('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'expected_fingerprint' => $token,
        ]))->assertStatus(409);
    }

    public function test_publish_rejects_an_unsafe_legacy_slug_without_changing_status(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        DB::table('POST_pena')->where('ID_POST', $id)->update(['LINK_POST' => 'HTTPS://unsafe.example/path']);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->postJson('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])
            ->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'STATUS_POST' => 'PO']);
    }

    public function test_publish_rejects_invalid_legacy_titles_without_changing_status(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        foreach (['', ' ', 'A', str_repeat('a', 72), 'Título 😀'] as $title) {
            DB::table('POST_pena')->where('ID_POST', $id)->update(['TITULO_POST' => $title]);
            $token = app(PostEditor::class)->find($id)['fingerprint'];
            $this->actingAs($admin)->postJson('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])
                ->assertUnprocessable()->assertJsonValidationErrors('title');
            $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'STATUS_POST' => 'PO']);
        }
        $this->assertSame(0, DB::table('pena_content_audit')->where('action', 'post.publish')->count());
    }

    public function test_publish_rejects_unlinked_or_orphaned_legacy_categories(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        DB::table('POST_pena')->where('ID_POST', $id)->update(['ID_CATEGORIA' => null]);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->postJson('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('main_category_id');

        DB::table('POST_pena')->where('ID_POST', $id)->update(['ID_CATEGORIA' => 2]);
        DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->where('ID_CATEGORIA', 2)->delete();
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->postJson('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('main_category_id');

        DB::table('POST_pena')->where('ID_POST', $id)->update(['ID_CATEGORIA' => 1]);
        DB::table('CATEGORIA_POST_pena')->insert(['ID_POST' => $id, 'ID_CATEGORIA' => 99]);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->postJson('/admin/posts/'.$id.'/publish', ['expected_fingerprint' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('category_ids');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'STATUS_POST' => 'PO']);
        $this->assertSame(0, DB::table('pena_content_audit')->where('action', 'post.publish')->count());
    }

    public function test_description_respects_the_legacy_text_byte_limit_in_http_and_service(): void
    {
        $admin = $this->linkedAdmin();
        $this->actingAs($admin)->post('/admin/posts', $this->input([
            'description' => str_repeat('a', PostEditor::DESCRIPTION_MAX_BYTES),
        ]))->assertRedirect();
        $id = (int) DB::table('POST_pena')->where('LINK_POST', 'agua-de-piscina-limpa')->value('ID_POST');
        $this->assertSame(PostEditor::DESCRIPTION_MAX_BYTES, strlen((string) DB::table('POST_pena')->where('ID_POST', $id)->value('DESCRICAO_POST')));

        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->put('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'description' => str_repeat('á', 32767).'a',
            'expected_fingerprint' => $token,
        ]))->assertRedirect();
        $stored = (string) DB::table('POST_pena')->where('ID_POST', $id)->value('DESCRICAO_POST');
        $this->assertSame(PostEditor::DESCRIPTION_MAX_BYTES, strlen($stored));

        $oversized = str_repeat('á', 32768);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $this->actingAs($admin)->putJson('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'description' => $oversized,
            'expected_fingerprint' => $token,
        ]))->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertSame($stored, DB::table('POST_pena')->where('ID_POST', $id)->value('DESCRICAO_POST'));

        try {
            app(PostEditor::class)->update($admin, $id, $this->input([
                'slug' => 'agua-de-piscina-limpa', 'description' => $oversized,
                'expected_fingerprint' => $token,
            ]));
            $this->fail('A gravação direta aceitou uma descrição maior que TEXT.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('description', $error->errors());
        }
        $this->assertSame($stored, DB::table('POST_pena')->where('ID_POST', $id)->value('DESCRICAO_POST'));
    }

    public function test_validation_rejects_duplicate_slug_bad_fields_unicode_and_invalid_categories(): void
    {
        $admin = $this->linkedAdmin();
        $this->created($admin);
        foreach ([
            ['title' => str_repeat('a', 72)],
            ['snippet' => str_repeat('a', 157)],
            ['cover_url' => str_repeat('a', 201)],
            ['title' => ['array']],
            ['slug' => 'AGUA-DE-PISCINA-LIMPA'],
            ['slug' => 'agua-de-piscina-limpa'],
            ['title' => 'Piscina 😀', 'slug' => 'piscina-emoji'],
            ['main_category_id' => 9],
            ['category_ids' => [1, 1]],
            ['cover_url' => 'javascript:alert(1)'],
        ] as $change) {
            $this->actingAs($admin)->postJson('/admin/posts', $this->input($change))->assertStatus(422);
        }
        $this->assertDatabaseCount('POST_pena', 3);
    }

    public function test_audit_failure_rolls_back_legacy_row_and_category_links(): void
    {
        $admin = $this->linkedAdmin();
        $id = $this->created($admin);
        $token = app(PostEditor::class)->find($id)['fingerprint'];
        $audit = Mockery::mock(ContentAudit::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('synthetic audit failure'));
        $this->app->instance(ContentAudit::class, $audit);
        $this->actingAs($admin)->put('/admin/posts/'.$id, $this->input([
            'slug' => 'agua-de-piscina-limpa', 'title' => 'Não deve persistir',
            'category_ids' => [1], 'expected_fingerprint' => $token,
        ]))->assertStatus(503);
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => $id, 'TITULO_POST' => 'Água de piscina limpa']);
        $this->assertSame(2, DB::table('CATEGORIA_POST_pena')->where('ID_POST', $id)->count());
    }
}
