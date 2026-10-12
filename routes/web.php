<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Queue;
use App\Livewire\Settings\Libraries;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Login::class)
    ->middleware(['auth.enabled', 'guest'])
    ->name('login');

Route::post('/logout', LogoutController::class)
    ->middleware(['auth.enabled', 'auth'])
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', Dashboard::class)->name('home');
    Route::livewire('/queue', Queue::class)->name('queue.index');
    Route::redirect('/settings', '/settings/libraries')->name('settings.index');
    Route::livewire('/settings/libraries', Libraries::class)->name('settings.libraries');
    Route::redirect('/libraries', '/settings/libraries')->name('libraries.index');
});
