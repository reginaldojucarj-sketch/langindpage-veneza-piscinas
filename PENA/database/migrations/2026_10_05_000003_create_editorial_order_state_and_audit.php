<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pena_editorial_state', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(1);
            $table->char('published_digest', 64);
            $table->timestamps();
        });

        Schema::create('pena_editorial_audit', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->uuid('correlation_id');
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('outcome', 24);
            $table->unsignedBigInteger('revision_before')->nullable();
            $table->unsignedBigInteger('revision_after')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('pena_editorial_state')->insertOrIgnore([
            'id' => 1,
            'revision' => 1,
            'published_digest' => hash('sha256', ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('A remoção da versão e auditoria editorial exige procedimento manual e backup.');
    }
};
