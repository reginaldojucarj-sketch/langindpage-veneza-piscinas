<?php

use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostOrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\ApiDocumentationController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Middleware\EnsureActiveAdminSession;
use Illuminate\Support\Facades\Route;

$apiHost = config('pena.api_host');
$apiRoutes = Route::middleware(['auth', EnsureActiveAdminSession::class, 'can:manage-users']);
if ($apiHost) {
    $apiRoutes->domain($apiHost);
    Route::domain($apiHost)->middleware(['auth', EnsureActiveAdminSession::class, 'can:manage-users'])
        ->get('/', [ApiDocumentationController::class, 'show']);
}
if ($apiHost || ! app()->isProduction()) {
    $apiRoutes->group(function () {
        Route::get('docs', [ApiDocumentationController::class, 'show'])->name('api.admin.docs');
        Route::get('openapi/admin-v1.json', [ApiDocumentationController::class, 'specification'])->name('api.admin.spec');
        Route::prefix('api/admin/v1')->name('api.admin.v1.')->group(function () {
            Route::get('me', [AdminApiController::class, 'me'])->name('me');
            Route::get('users', [AdminApiController::class, 'users'])->name('users.index');
            Route::post('users', [AdminApiController::class, 'createUser'])->middleware('throttle:20,1')->name('users.store');
            Route::get('users/{user}', [AdminApiController::class, 'user'])->whereNumber('user')->name('users.show');
            Route::patch('users/{user}', [AdminApiController::class, 'updateUser'])->whereNumber('user')->middleware('throttle:20,1')->name('users.update');
            Route::get('authors', [AdminApiController::class, 'authors'])->name('authors.index');
            Route::post('authors', [AdminApiController::class, 'createAuthor'])->middleware('throttle:20,1')->name('authors.store');
            Route::get('authors/{author}', [AdminApiController::class, 'author'])->whereNumber('author')->name('authors.show');
            Route::patch('authors/{author}', [AdminApiController::class, 'updateAuthor'])->whereNumber('author')->middleware('throttle:20,1')->name('authors.update');
            Route::patch('authors/{author}/activate', [AdminApiController::class, 'activateAuthor'])->whereNumber('author')->middleware('throttle:20,1')->name('authors.activate');
            Route::patch('authors/{author}/deactivate', [AdminApiController::class, 'deactivateAuthor'])->whereNumber('author')->middleware('throttle:20,1')->name('authors.deactivate');
            Route::get('media', [AdminApiController::class, 'media'])->name('media.index');
            Route::post('media', [AdminApiController::class, 'uploadMedia'])->middleware('throttle:20,1')->name('media.store');
            Route::get('media/{media}', [AdminApiController::class, 'medium'])->whereUuid('media')->name('media.show');
            Route::patch('media/{media}', [AdminApiController::class, 'updateMedia'])->whereUuid('media')->middleware('throttle:20,1')->name('media.update');
            Route::patch('media/{media}/activate', [AdminApiController::class, 'activateMedia'])->whereUuid('media')->middleware('throttle:20,1')->name('media.activate');
            Route::patch('media/{media}/deactivate', [AdminApiController::class, 'deactivateMedia'])->whereUuid('media')->middleware('throttle:20,1')->name('media.deactivate');
            Route::get('posts/order', [AdminApiController::class, 'order'])->name('posts.order');
            Route::put('posts/order', [AdminApiController::class, 'saveOrder'])->middleware('throttle:20,1')->name('posts.order.update');
            Route::get('posts', [AdminApiController::class, 'posts'])->name('posts.index');
            Route::post('posts', [AdminApiController::class, 'createPost'])->middleware('throttle:20,1')->name('posts.store');
            Route::get('posts/{post}', [AdminApiController::class, 'post'])->whereNumber('post')->name('posts.show');
            Route::put('posts/{post}', [AdminApiController::class, 'updatePost'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.update');
            Route::post('posts/{post}/publish', [AdminApiController::class, 'publishPost'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.publish');
            Route::post('posts/{post}/hide', [AdminApiController::class, 'hidePost'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.hide');
            Route::post('posts/{post}/delete', [AdminApiController::class, 'deletePost'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.delete');
        });
    });
}

Route::redirect('/', '/admin');
Route::get('/media/{media}', PublicMediaController::class)->whereUuid('media')->name('media.public');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    });

    Route::middleware(['auth', EnsureActiveAdminSession::class])->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');
        Route::get('posts/order', [PostOrderController::class, 'index'])->middleware('can:order-posts')->name('posts.order.index');
        Route::post('posts/order', [PostOrderController::class, 'update'])->middleware('can:order-posts')->name('posts.order.update');
        Route::middleware('can:edit-content')->group(function () {
            Route::get('posts', [PostController::class, 'index'])->name('posts.index');
            Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
            Route::post('posts', [PostController::class, 'store'])->name('posts.store');
            Route::get('posts/{post}/edit', [PostController::class, 'edit'])->whereNumber('post')->name('posts.edit');
            Route::put('posts/{post}', [PostController::class, 'update'])->whereNumber('post')->name('posts.update');
            Route::get('posts/{post}/preview', [PostController::class, 'preview'])->whereNumber('post')->name('posts.preview');
            Route::post('posts/{post}/publish', [PostController::class, 'publish'])->middleware('can:publish-content')->whereNumber('post')->name('posts.publish');
            Route::post('posts/{post}/hide', [PostController::class, 'hide'])->middleware('can:publish-content')->whereNumber('post')->name('posts.hide');
            Route::post('posts/{post}/delete', [PostController::class, 'delete'])->middleware('can:delete-content')->whereNumber('post')->name('posts.delete');
            Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
            Route::get('authors/create', [AuthorController::class, 'create'])->name('authors.create');
            Route::post('authors', [AuthorController::class, 'store'])->name('authors.store');
            Route::get('authors/{author}/edit', [AuthorController::class, 'edit'])->whereNumber('author')->name('authors.edit');
            Route::patch('authors/{author}', [AuthorController::class, 'update'])->whereNumber('author')->name('authors.update');
            Route::patch('authors/{author}/deactivate', [AuthorController::class, 'deactivate'])->whereNumber('author')->name('authors.deactivate');
            Route::patch('authors/{author}/activate', [AuthorController::class, 'activate'])->whereNumber('author')->name('authors.activate');
            Route::get('media', [MediaController::class, 'index'])->name('media.index');
            Route::post('media', [MediaController::class, 'store'])->name('media.store');
            Route::patch('media/{media}/alt-text', [MediaController::class, 'updateAltText'])->whereUuid('media')->name('media.alt-text');
            Route::patch('media/{media}/deactivate', [MediaController::class, 'deactivate'])->whereUuid('media')->name('media.deactivate');
            Route::patch('media/{media}/activate', [MediaController::class, 'activate'])->whereUuid('media')->name('media.activate');
        });
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('can:manage-users')->name('users.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->middleware('can:manage-users')->name('users.update');
        Route::post('users/{user}/password', [UserController::class, 'resetPassword'])->middleware(['can:manage-users', 'throttle:5,1'])->name('users.password');
        Route::get('account/password', [UserController::class, 'passwordForm'])->name('account.password');
        Route::post('account/password', [UserController::class, 'passwordUpdate'])->middleware('throttle:5,1')->name('account.password.update');
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    });
});
