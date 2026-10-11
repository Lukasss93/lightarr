<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAuthenticationEnabled;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Livewire::addPersistentMiddleware([
            Authenticate::class,
            EnsureAuthenticationEnabled::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
