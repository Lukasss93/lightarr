<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('logs out and replaces the session and CSRF token', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    session()->put('private-data', 'previous-session');
    $sessionId = session()->getId();
    $csrfToken = session()->token();

    post(route('logout'))
        ->assertRedirectToRoute('login')
        ->assertSessionMissing('private-data');

    assertGuest();
    expect(session()->getId())->not->toBe($sessionId);
    expect(session()->token())->not->toBe($csrfToken);
});

it('requires login before logout', function (): void {
    post(route('logout'))->assertRedirectToRoute('login');
});

it('rejects logout over GET', function (): void {
    get(route('logout'))->assertMethodNotAllowed();
});

it('requires a CSRF token for logout', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    app()->detectEnvironment(fn (): string => 'local');

    post(route('logout'))->assertStatus(419);

    assertAuthenticatedAs($user);
});

it('redirects disabled logout to home without changing the session', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    session()->put('private-data', 'current-session');
    Config::set('auth.enabled', false);

    post(route('logout'))->assertRedirectToRoute('home')->assertSessionHas('private-data', 'current-session');

    assertAuthenticatedAs($user);
});
