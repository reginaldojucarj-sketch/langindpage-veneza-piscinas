<?php

use App\Http\Controllers\Api\PublicPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('posts', [PublicPostController::class, 'index']);
    Route::get('posts/{id}', [PublicPostController::class, 'show'])->whereNumber('id');
});
