<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PostOrderController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
        Route::get('posts/order', [PostOrderController::class, 'index'])->name('posts.order.index');
        Route::post('posts/order', [PostOrderController::class, 'update'])->name('posts.order.update');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    });
});
