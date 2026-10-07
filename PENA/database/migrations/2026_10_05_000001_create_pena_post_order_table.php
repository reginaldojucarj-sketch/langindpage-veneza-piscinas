<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pena_post_order', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('post_id')->primary();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('A remoção da ordem editorial exige procedimento manual e backup.');
    }
};
