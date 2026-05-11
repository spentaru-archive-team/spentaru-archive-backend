<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::define('viewLogViewer', fn (?User $user): bool => $user?->role === 'admin');
        Gate::define('downloadLogFile', fn (?User $user): bool => $user?->role === 'admin');
        Gate::define('downloadLogFolder', fn (?User $user): bool => $user?->role === 'admin');
        Gate::define('deleteLogFile', fn (?User $user): bool => $user?->role === 'admin');
        Gate::define('deleteLogFolder', fn (?User $user): bool => $user?->role === 'admin');
    }
}
