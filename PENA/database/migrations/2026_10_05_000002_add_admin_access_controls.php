<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not silently convert an existing administrative table.
        if (DB::getDriverName() === 'mysql') {
            $table = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', ['pena_admin_users']);
            if (strtolower($table?->engine ?? '') !== 'innodb') {
                throw new RuntimeException('pena_admin_users must use InnoDB. Review its engine separately before migrating.');
            }
        }

        Schema::table('pena_admin_users', function (Blueprint $table) {
            $table->string('role', 20)->default('editor');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('auth_version')->default(1);
            $table->unsignedInteger('legacy_person_id')->nullable()->unique();
        });
        // All accounts predating this migration already had unrestricted admin access.
        DB::table('pena_admin_users')->update(['role' => 'admin']);

        Schema::create('pena_admin_access_lock', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('id')->primary();
        });
        DB::table('pena_admin_access_lock')->insert(['id' => 1]);

        Schema::create('pena_admin_audit', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('target_id');
            $table->string('action', 40);
            $table->text('changed_fields');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Access-control rollback requires a reviewed backup and manual procedure.');
    }
};
