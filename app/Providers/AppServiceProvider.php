<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAuthenticationEnabled;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\DevCommands;
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
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('schedule:work', 'scheduler');
        }

        Livewire::addPersistentMiddleware([
            Authenticate::class,
            EnsureAuthenticationEnabled::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
