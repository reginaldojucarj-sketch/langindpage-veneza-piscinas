<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pena_media', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->char('sha256', 64)->unique();
            $table->string('original_name', 255);
            $table->string('source_mime', 50);
            $table->string('public_mime', 50);
            $table->unsignedBigInteger('original_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text', 255);
            $table->string('original_path', 255)->unique();
            $table->string('derivative_path', 255)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('uploaded_by')->index();
            $table->timestamps();
        });

        Schema::create('pena_authors', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('legacy_author_id')->nullable()->unique();
            $table->unsignedInteger('person_id')->index();
            $table->string('signature', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->string('legacy_photo_url', 200)->nullable();
            $table->unsignedInteger('legacy_image_id')->nullable();
            $table->foreignUuid('photo_media_id')->nullable()->references('id')->on('pena_media')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pena_post_author_assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('post_id')->primary();
            $table->foreignId('author_id')->constrained('pena_authors')->restrictOnDelete();
            $table->string('author_signature_snapshot', 50);
            $table->string('source', 20)->default('admin');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pena_post_media_assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('post_id')->primary();
            $table->foreignUuid('media_id')->constrained('pena_media')->restrictOnDelete();
            $table->string('alt_text_snapshot', 255)->default('');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pena_content_audit', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 80);
            $table->string('entity_type', 40);
            $table->string('entity_id', 80)->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->copyLegacyAuthorsAndSnapshots();
    }

    private function copyLegacyAuthorsAndSnapshots(): void
    {
        if (! Schema::hasTable('AUTOR_pena') || ! Schema::hasTable('POST_pena')) {
            throw new RuntimeException('As tabelas legadas de autores e artigos precisam existir antes desta migração.');
        }

        $now = now();
        $authors = DB::table('AUTOR_pena')->select([
            'ID_AUTOR', 'ID_PESSOA', 'ASSINATURA_AUTOR', 'LINK_AUTOR', 'DESCRICAO_AUTOR',
            'URL_IMAGEM_AUTOR', 'ID_IMAGENS', 'DATA_CRIACAO_AUTOR',
        ])->orderBy('ID_AUTOR')->get();

        foreach ($authors->chunk(100) as $chunk) {
            DB::table('pena_authors')->insertOrIgnore($chunk->map(fn ($author) => [
                'legacy_author_id' => (int) $author->ID_AUTOR,
                'person_id' => (int) $author->ID_PESSOA,
                'signature' => $author->ASSINATURA_AUTOR,
                'slug' => $author->LINK_AUTOR,
                'description' => $author->DESCRICAO_AUTOR,
                'legacy_photo_url' => $author->URL_IMAGEM_AUTOR,
                'legacy_image_id' => $author->ID_IMAGENS === null ? null : (int) $author->ID_IMAGENS,
                // The meaning of STATUS_AUTOR has not been confirmed. Keep old authors usable.
                'is_active' => true,
                'created_at' => $author->DATA_CRIACAO_AUTOR ?: $now,
                'updated_at' => $now,
            ])->all());
        }

        $assignments = DB::table('POST_pena as posts')
            ->join('pena_authors as authors', 'authors.legacy_author_id', '=', 'posts.ID_AUTOR')
            ->select('posts.ID_POST', 'authors.id', 'authors.signature')
            ->whereNotNull('posts.ID_AUTOR')->where('posts.ID_AUTOR', '>', 0)
            ->orderBy('posts.ID_POST')->get();

        foreach ($assignments->chunk(500) as $chunk) {
            DB::table('pena_post_author_assignments')->insertOrIgnore($chunk->map(fn ($assignment) => [
                'post_id' => (int) $assignment->ID_POST,
                'author_id' => (int) $assignment->id,
                'author_signature_snapshot' => $assignment->signature,
                'source' => 'legacy',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Autores, vínculos históricos e mídias exigem backup e remoção manual revisada.');
    }
};
