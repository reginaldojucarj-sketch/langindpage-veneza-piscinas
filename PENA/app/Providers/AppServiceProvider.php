<?php

namespace App\Providers;

use App\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-users', fn (AdminUser $user) => $user->isAdministrator());
        Gate::define('edit-content', fn (AdminUser $user) => $user->is_active && in_array($user->role, ['admin', 'editor'], true));
        foreach (['publish-content', 'delete-content', 'order-posts'] as $ability) {
            Gate::define($ability, fn (AdminUser $user) => $user->isAdministrator());
        }
    }
}
