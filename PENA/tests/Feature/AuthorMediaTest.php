<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EditorialAuthor;
use App\Models\EditorialMedia;
use App\Services\AuthorLibrary;
use App\Services\ContentAudit;
use App\Services\MediaFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\LegacyDatabaseTestCase;

class AuthorMediaTest extends LegacyDatabaseTestCase
{
    private function syntheticImage(string $extension): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pena-image-');
        $image = imagecreatetruecolor(24, 16);
        ob_start();
        $written = match ($extension) {
            'jpg' => imagejpeg($image, null, 82),
            'png' => imagepng($image, null, 8),
            'webp' => imagewebp($image, null, 80),
        };
        $contents = ob_get_clean();
        unset($image);
        file_put_contents($path, $contents);
        $mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        return new UploadedFile($path, 'fixture.'.$extension, $mimes[$extension], UPLOAD_ERR_OK, true);
    }

    public function test_author_and_media_management_require_an_active_editorial_session(): void
    {
        $this->get('/admin/authors')->assertRedirect('/admin/login');
        $this->get('/admin/media')->assertRedirect('/admin/login');
        $this->post('/admin/authors')->assertRedirect('/admin/login');
        $this->post('/admin/media')->assertRedirect('/admin/login');
        $this->get('/media/'.fake()->uuid())->assertNotFound();

        $editor = AdminUser::create(['name' => 'Editora', 'email' => 'editora@example.com', 'password' => 'senha-teste-segura-123']);
        $this->actingAs($editor)->get('/admin/authors')->assertOk();
        $this->actingAs($editor)->get('/admin/media')->assertOk();
    }

    public function test_authors_can_be_created_updated_and_deactivated_without_changing_legacy_history(): void
    {
        $admin = $this->admin();
        $originalLegacyRow = DB::table('AUTOR_pena')->where('ID_AUTOR', 9)->first();
        $author = EditorialAuthor::query()->where('legacy_author_id', 9)->firstOrFail();

        $this->actingAs($admin)->get('/admin/authors')->assertOk()
            ->assertSee('Autoria sintética')->assertSee('Pessoa Sintética');
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id, [
            'expected_version' => 1,
            'signature' => 'Assinatura revisada',
            'slug' => 'assinatura-revisada',
            'description' => 'Descrição revisada.',
            'photo_media_id' => '',
        ])->assertRedirect('/admin/authors/'.$author->id.'/edit');

        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'signature' => 'Assinatura revisada', 'version' => 2]);
        $this->assertDatabaseHas('pena_post_author_assignments', [
            'post_id' => 1, 'author_id' => $author->id, 'author_signature_snapshot' => 'Autoria sintética',
        ]);
        $this->getJson('/api/public/posts/1')->assertOk()->assertJsonPath('data.author', 'Autoria sintética');

        $this->actingAs($admin)->patch('/admin/authors/'.$author->id.'/deactivate', ['expected_version' => 2])
            ->assertRedirect('/admin/authors');
        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'is_active' => false, 'version' => 3]);
        $this->assertSame($originalLegacyRow->ASSINATURA_AUTOR, DB::table('AUTOR_pena')->where('ID_AUTOR', 9)->value('ASSINATURA_AUTOR'));
        $this->getJson('/api/public/posts/1')->assertOk()->assertJsonPath('data.author', 'Autoria sintética');
        $this->assertDatabaseHas('pena_content_audit', ['action' => 'author.updated', 'entity_id' => (string) $author->id]);
        $this->assertDatabaseHas('pena_content_audit', ['action' => 'author.deactivated', 'entity_id' => (string) $author->id]);
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id.'/activate', ['expected_version' => 3])
            ->assertRedirect('/admin/authors/'.$author->id.'/edit');
        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'is_active' => true, 'version' => 4]);
    }

    public function test_new_author_requires_explicit_person_and_unique_signature_and_slug(): void
    {
        $admin = $this->admin();
        $payload = [
            'person_id' => 1,
            'signature' => 'Nova autoria',
            'slug' => 'nova-autoria',
            'description' => 'Texto sintético.',
        ];
        $this->actingAs($admin)->post('/admin/authors', $payload)->assertRedirect();
        $this->assertDatabaseHas('pena_authors', ['signature' => 'Nova autoria', 'slug' => 'nova-autoria', 'person_id' => 1, 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/authors', $payload + ['slug' => 'outro-slug'])
            ->assertSessionHasErrors('signature');
        $this->actingAs($admin)->post('/admin/authors', [
            'person_id' => 999, 'signature' => 'Outra autoria', 'slug' => 'outra-autoria',
        ])->assertSessionHasErrors('person_id');
        $this->assertDatabaseCount('pena_authors', 2);
    }

    public function test_author_search_treats_like_metacharacters_as_literal_text(): void
    {
        $author = EditorialAuthor::query()->create([
            'person_id' => 1,
            'signature' => 'Autor %_! literal',
            'slug' => 'autor-literal',
            'is_active' => true,
            'version' => 1,
        ]);
        $library = app(AuthorLibrary::class);

        foreach (['%', '_', '!', '%_!'] as $term) {
            $this->assertSame([$author->id], array_column($library->listing($term), 'id'));
        }

        $this->actingAs($this->admin())->get('/admin/authors?q=%25')->assertOk()
            ->assertSee('Autor %_! literal')
            ->assertDontSee('Autoria sintética');
    }

    public function test_stale_author_form_does_not_overwrite_a_newer_change(): void
    {
        $admin = $this->admin();
        $author = EditorialAuthor::query()->where('legacy_author_id', 9)->firstOrFail();
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id, [
            'expected_version' => 1, 'signature' => 'Atualizada', 'slug' => 'atualizada',
        ])->assertRedirect();
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id, [
            'expected_version' => 1, 'signature' => 'Rascunho obsoleto', 'slug' => 'rascunho-obsoleto',
        ])->assertSessionHasErrors('expected_version');
        $this->assertDatabaseMissing('pena_authors', ['signature' => 'Rascunho obsoleto']);
    }

    public function test_author_edit_preserves_current_photo_even_when_old_and_inactive(): void
    {
        $admin = $this->admin();
        $author = EditorialAuthor::query()->where('legacy_author_id', 9)->firstOrFail();
        $currentId = (string) Str::uuid();
        $base = [
            'sha256' => hash('sha256', 'current author photo'), 'original_name' => 'foto-atual.jpg',
            'source_mime' => 'image/jpeg', 'public_mime' => 'image/jpeg', 'original_bytes' => 50,
            'width' => 100, 'height' => 100, 'alt_text' => 'Foto do autor',
            'original_path' => $currentId.'.jpg', 'derivative_path' => $currentId.'.jpg',
            'is_active' => true, 'uploaded_by' => $admin->id, 'created_at' => now()->subYear(), 'updated_at' => now(),
        ];
        DB::table('pena_media')->insert(['id' => $currentId, ...$base]);
        for ($index = 0; $index < 300; $index++) {
            $id = (string) Str::uuid();
            DB::table('pena_media')->insert([
                ...$base, 'id' => $id, 'sha256' => hash('sha256', 'newer photo '.$index),
                'original_name' => 'foto-'.$index.'.jpg', 'original_path' => $id.'.jpg',
                'derivative_path' => $id.'.jpg', 'created_at' => now()->subMinutes($index),
            ]);
        }
        DB::table('pena_authors')->where('id', $author->id)->update(['photo_media_id' => $currentId]);

        $this->actingAs($admin)->get('/admin/authors/'.$author->id.'/edit')->assertOk()
            ->assertSee('value="'.$currentId.'" selected', false);

        DB::table('pena_media')->where('id', $currentId)->update(['is_active' => false]);

        $this->actingAs($admin)->get('/admin/authors/'.$author->id.'/edit')->assertOk()
            ->assertSee('value="'.$currentId.'" selected', false)
            ->assertSee('vínculo atual preservado');
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id, [
            'expected_version' => 1, 'signature' => 'Assinatura atualizada', 'slug' => 'assinatura-atualizada',
            'description' => 'Edição sem trocar a foto.', 'photo_media_id' => $currentId,
        ])->assertRedirect('/admin/authors/'.$author->id.'/edit');
        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'photo_media_id' => $currentId]);

        $this->actingAs($admin)->patch('/admin/authors/'.$author->id, [
            'expected_version' => 2, 'signature' => 'Outra alteração', 'slug' => 'outra-alteracao',
        ])->assertRedirect('/admin/authors/'.$author->id.'/edit');
        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'photo_media_id' => $currentId]);
    }

    public function test_person_name_is_not_substituted_for_missing_legacy_author(): void
    {
        DB::table('POST_pena')->insert([
            'ID_POST' => 4, 'TITULO_POST' => 'Sem autoria confirmada', 'STATUS_POST' => 'PP',
            'ID_AUTOR' => 0, 'ID_PESSOA' => 1,
        ]);

        $this->getJson('/api/public/posts/4')->assertOk()->assertJsonPath('data.author', null);
        $this->assertDatabaseMissing('pena_post_author_assignments', ['post_id' => 4]);
    }

    public function test_author_cannot_be_deleted_and_version_conflicts_are_rejected(): void
    {
        $admin = $this->admin();
        $author = EditorialAuthor::query()->where('legacy_author_id', 9)->firstOrFail();
        $this->actingAs($admin)->delete('/admin/authors/'.$author->id)->assertStatus(405);
        $this->actingAs($admin)->patch('/admin/authors/'.$author->id.'/deactivate', ['expected_version' => 99])
            ->assertSessionHasErrors('expected_version');
        $this->assertDatabaseHas('pena_authors', ['id' => $author->id, 'is_active' => true]);
    }

    public function test_valid_image_is_stored_privately_and_only_normalized_derivative_is_public(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $file = UploadedFile::fake()->image('piscina.png', 2400, 1200)->mimeType('application/x-php');

        $this->actingAs($admin)->post('/admin/media', ['file' => $file, 'alt_text' => 'Piscina em dia ensolarado'])
            ->assertRedirect('/admin/media');

        $media = EditorialMedia::query()->firstOrFail();
        $storedName = $media->id.'.png';
        Storage::disk('pena_originals')->assertExists($storedName);
        Storage::disk('pena_derivatives')->assertExists($storedName);
        $derivative = Storage::disk('pena_derivatives')->get($storedName);
        $dimensions = getimagesizefromstring($derivative);
        $this->assertSame('image/png', $media->public_mime);
        $this->assertSame('image/png', $dimensions['mime']);
        $this->assertSame(1800, $dimensions[0]);
        $this->assertSame(900, $dimensions[1]);
        $this->assertSame(1800, $media->width);
        $this->assertSame(900, $media->height);

        $this->actingAs($admin)->get('/admin/media')->assertOk()
            ->assertSee('Selecionar para artigo')->assertDontSee($media->sha256)
            ->assertDontSee($media->original_path)->assertDontSee($media->derivative_path);
        $this->actingAs($admin)->patch('/admin/media/'.$media->id.'/alt-text', [
            'expected_version' => 1, 'alt_text' => 'Imagem de uma piscina residencial',
        ])->assertRedirect('/admin/media');
        $this->assertDatabaseHas('pena_media', ['id' => $media->id, 'alt_text' => 'Imagem de uma piscina residencial', 'version' => 2]);
        $this->assertDatabaseHas('pena_content_audit', ['action' => 'media.alt_text_updated', 'entity_id' => $media->id]);
        $this->actingAs($admin)->patch('/admin/media/'.$media->id.'/alt-text', [
            'expected_version' => 1, 'alt_text' => 'Descrição obsoleta',
        ])->assertSessionHasErrors('expected_version');
        $this->assertDatabaseMissing('pena_media', ['id' => $media->id, 'alt_text' => 'Descrição obsoleta']);
        $response = $this->get('/media/'.$media->id)->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $response->baseResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('<script', $response->streamedContent());
        $this->assertDatabaseHas('pena_content_audit', ['action' => 'media.uploaded', 'entity_id' => $media->id]);
    }

    public function test_jpeg_png_and_webp_are_normalized_to_their_verified_types(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromwebp')) {
            $this->markTestSkipped('Este contêiner não oferece suporte a WebP na extensão GD.');
        }
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $files = array_map(fn ($extension) => $this->syntheticImage($extension), ['jpg', 'png', 'webp']);

        try {
            foreach ($files as $file) {
                $this->actingAs($admin)->post('/admin/media', ['file' => $file, 'alt_text' => 'Fixture de '.pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)])
                    ->assertRedirect('/admin/media');
            }
        } finally {
            foreach ($files as $file) {
                @unlink($file->getRealPath());
            }
        }

        $this->assertDatabaseCount('pena_media', 3);
        $this->assertEqualsCanonicalizing(['image/jpeg', 'image/png', 'image/webp'], EditorialMedia::query()->pluck('public_mime')->all());
        foreach (EditorialMedia::query()->get() as $media) {
            $bytes = Storage::disk('pena_derivatives')->get($media->id.'.'.match ($media->public_mime) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            });
            $this->assertSame($media->public_mime, getimagesizefromstring($bytes)['mime']);
        }
    }

    public function test_empty_oversized_fake_mime_double_extension_svg_and_traversal_are_handled_safely(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();

        foreach ([
            UploadedFile::fake()->create('empty.jpg', 0, 'image/jpeg'),
            UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'),
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "not an image";'),
            UploadedFile::fake()->image('payload.php.jpg', 24, 24),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg onload="alert(1)"></svg>'),
            new UploadedFile(UploadedFile::fake()->image('traversal.png', 24, 24)->getPathname(), '../traversal.png', 'image/png', UPLOAD_ERR_OK, true),
            UploadedFile::fake()->image('oversized-dimensions.png', 6001, 1),
        ] as $invalid) {
            $this->actingAs($admin)->from('/admin/media')->post('/admin/media', [
                'file' => $invalid, 'alt_text' => 'Imagem de teste',
            ])->assertRedirect('/admin/media')->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('pena_media', 0);
        $this->assertSame([], Storage::disk('pena_originals')->allFiles());
        $this->assertSame([], Storage::disk('pena_derivatives')->allFiles());
    }

    public function test_duplicate_content_is_rejected_without_orphan_files(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $first = UploadedFile::fake()->image('primeira.png', 32, 32);
        $this->actingAs($admin)->post('/admin/media', ['file' => $first, 'alt_text' => 'Imagem repetida'])->assertRedirect();
        $second = new UploadedFile($first->getPathname(), 'segunda.png', 'image/png', UPLOAD_ERR_OK, true);
        $this->actingAs($admin)->from('/admin/media')->post('/admin/media', ['file' => $second, 'alt_text' => 'Imagem repetida'])
            ->assertRedirect('/admin/media')->assertSessionHasErrors('file');

        $this->assertDatabaseCount('pena_media', 1);
        $this->assertCount(1, Storage::disk('pena_originals')->allFiles());
        $this->assertCount(1, Storage::disk('pena_derivatives')->allFiles());
    }

    public function test_storage_failure_leaves_no_usable_record_or_public_orphan(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $storage = Mockery::mock(MediaFileStorage::class);
        $storage->shouldReceive('putStream')->once()->andReturnTrue();
        $storage->shouldReceive('put')->once()->andThrow(new \RuntimeException('synthetic storage failure'));
        $storage->shouldReceive('delete')->twice()->andReturnTrue();
        $this->app->instance(MediaFileStorage::class, $storage);
        $this->actingAs($admin)->post('/admin/media', [
            'file' => UploadedFile::fake()->image('falha.png', 32, 32), 'alt_text' => 'Falha sintética',
        ])->assertStatus(503);
        $this->assertDatabaseCount('pena_media', 0);
        $this->assertSame([], Storage::disk('pena_originals')->allFiles());
        $this->assertSame([], Storage::disk('pena_derivatives')->allFiles());
    }

    public function test_database_failure_compensates_both_private_file_writes(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $audit = Mockery::mock(ContentAudit::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('synthetic database failure'));
        $this->app->instance(ContentAudit::class, $audit);
        $this->actingAs($admin)->post('/admin/media', [
            'file' => UploadedFile::fake()->image('falha-banco.png', 32, 32), 'alt_text' => 'Falha sintética',
        ])->assertStatus(503);
        $this->assertDatabaseCount('pena_media', 0);
        $this->assertSame([], Storage::disk('pena_originals')->allFiles());
        $this->assertSame([], Storage::disk('pena_derivatives')->allFiles());
    }

    public function test_media_in_use_cannot_be_deactivated_but_unused_file_is_preserved_on_deactivation(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/media', [
            'file' => UploadedFile::fake()->image('capa.png', 40, 30), 'alt_text' => 'Capa selecionável',
        ])->assertRedirect();
        $media = EditorialMedia::query()->firstOrFail();
        $path = $media->id.'.png';
        DB::table('POST_pena')->where('ID_POST', 1)->update(['URL_IMAGEM_POST' => route('media.public', $media->id)]);

        $this->actingAs($admin)->from('/admin/media')->patch('/admin/media/'.$media->id.'/deactivate')
            ->assertRedirect('/admin/media')->assertSessionHasErrors('media');
        $this->assertDatabaseHas('pena_media', ['id' => $media->id, 'is_active' => true]);
        Storage::disk('pena_derivatives')->assertExists($path);

        DB::table('POST_pena')->where('ID_POST', 1)->update(['URL_IMAGEM_POST' => null]);
        $this->actingAs($admin)->patch('/admin/media/'.$media->id.'/deactivate')->assertRedirect('/admin/media');
        $this->assertDatabaseHas('pena_media', ['id' => $media->id, 'is_active' => false]);
        Storage::disk('pena_originals')->assertExists($path);
        Storage::disk('pena_derivatives')->assertExists($path);
        $this->get('/media/'.$media->id)->assertNotFound();
        $this->actingAs($admin)->patch('/admin/media/'.$media->id.'/activate')->assertRedirect('/admin/media');
        $this->assertDatabaseHas('pena_media', ['id' => $media->id, 'is_active' => true]);
        $this->get('/media/'.$media->id)->assertOk();
    }

    public function test_legacy_media_catalog_is_empty_safe_and_never_previews_untrusted_hosts(): void
    {
        Storage::fake('pena_originals');
        Storage::fake('pena_derivatives');
        DB::table('POST_pena')->insert([
            'ID_POST' => 5, 'TITULO_POST' => 'Capa externa', 'STATUS_POST' => 'PO',
            'URL_IMAGEM_POST' => 'https://example-attacker.invalid/payload.svg',
        ]);
        DB::table('POST_pena')->insert([
            'ID_POST' => 6, 'TITULO_POST' => 'Imagem no corpo', 'STATUS_POST' => 'PO',
            'CONTEUDO_POST' => '<p><img src="/legado/foto.jpg" alt="Piscina"></p>',
        ]);
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get('/admin/media')->assertOk()
            ->assertSee('Mídias legadas em uso')->assertSee('Capa legada')->assertSee('Imagem incorporada em artigo')
            ->assertSee(rtrim((string) config('app.url'), '/').'/legado/foto.jpg');
        $this->assertStringNotContainsString('example-attacker.invalid/payload.svg', $response->getContent());
        $this->assertStringContainsString('Não disponível para seleção', $response->getContent());
    }

    public function test_public_api_uses_selected_media_for_new_posts_without_replacing_old_urls(): void
    {
        $admin = $this->admin();
        $id = (string) Str::uuid();
        DB::table('pena_media')->insert([
            'id' => $id, 'sha256' => hash('sha256', 'synthetic selected image'),
            'original_name' => 'selecionada.png', 'source_mime' => 'image/png', 'public_mime' => 'image/png',
            'original_bytes' => 25, 'width' => 12, 'height' => 8, 'alt_text' => 'Casa de máquinas',
            'original_path' => $id.'.png', 'derivative_path' => $id.'.png',
            'is_active' => true, 'uploaded_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('POST_pena')->insert([
            'ID_POST' => 7, 'TITULO_POST' => 'Artigo com mídia gerenciada', 'STATUS_POST' => 'PP',
            'ID_AUTOR' => 0, 'ID_PESSOA' => 1, 'URL_IMAGEM_POST' => null,
        ]);
        DB::table('POST_pena')->where('ID_POST', 1)->update(['URL_IMAGEM_POST' => 'https://pena.venezapiscinas.com.br/midia-legada.jpg']);
        DB::table('pena_post_media_assignments')->insert([
            'post_id' => 7, 'media_id' => $id, 'alt_text_snapshot' => 'Casa de máquinas',
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/public/posts/7')->assertOk()
            ->assertJsonPath('data.image', url('/media/'.$id))
            ->assertJsonPath('data.image_name', 'Casa de máquinas');
        $this->assertDatabaseHas('POST_pena', ['ID_POST' => 1, 'URL_IMAGEM_POST' => 'https://pena.venezapiscinas.com.br/midia-legada.jpg']);
    }
}
