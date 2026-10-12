<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureWorkspace;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Re-run our access middleware on every Livewire update request, not just the first page load.
        Livewire::addPersistentMiddleware([
            EnsureWorkspace::class,
            EnsureMember::class,
            EnsureClient::class,
            EnsureAdmin::class,
        ]);
    }
}
