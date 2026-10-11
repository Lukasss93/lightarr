<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Login::class)
    ->middleware(['auth.enabled', 'guest'])
    ->name('login');

Route::post('/logout', LogoutController::class)
    ->middleware(['auth.enabled', 'auth'])
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', Home::class)->name('home');
});
