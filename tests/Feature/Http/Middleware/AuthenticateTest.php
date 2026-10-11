<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('redirects guests to login and remembers their destination', function (): void {
    get(route('home'))
        ->assertRedirectToRoute('login')
        ->assertSessionHas('url.intended', route('home'));
});

it('returns 401 to unauthenticated JSON requests', function (): void {
    getJson(route('home'))->assertUnauthorized();
});

it('allows each account the same access and escapes its displayed name', function (): void {
    $user = User::factory()->create(['name' => '<script>alert("name")</script>']);

    actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertDontSee($user->name, false)
        ->assertSee('Log out');
});

it('allows guests into the app without creating a user when login is disabled', function (): void {
    Config::set('auth.enabled', false);

    get(route('home'))->assertOk()->assertDontSee('Log out');

    assertGuest();
    assertDatabaseCount('users', 0);
});

it('redirects the disabled login page to home', function (): void {
    Config::set('auth.enabled', false);

    get(route('login'))->assertRedirectToRoute('home');
});

it('skips session validation and hides account controls when login is disabled', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    get(route('home'))->assertOk();
    $user->update(['password' => 'changed-password']);
    Auth::forgetGuards();
    Config::set('auth.enabled', false);

    get(route('home'))->assertOk()->assertDontSee('Log out')->assertDontSee($user->name);

    assertDatabaseCount('users', 1);
});

it('leaves the health endpoint available to guests', function (): void {
    get('/up')->assertOk();
});

it('returns 401 for a Livewire update after the session expires', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    $page = get(route('home'))->assertOk();
    Auth::logout();
    session()->invalidate();
    Auth::forgetGuards();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])
        ->assertUnauthorized();
});

it('requires login on Livewire updates when authentication is enabled after page load', function (): void {
    Config::set('auth.enabled', false);
    $page = get(route('home'))->assertOk();
    Config::set('auth.enabled', true);

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])
        ->assertUnauthorized();
});

it('allows guest Livewire updates when authentication is disabled after page load', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    $page = get(route('home'))->assertOk();
    Auth::logout();
    Auth::forgetGuards();
    Config::set('auth.enabled', false);

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])
        ->assertOk()
        ->assertJsonStructure(['components' => [['effects' => ['html']]]]);

    assertGuest();
});

it('rejects a stale login form after authentication is disabled', function (): void {
    $user = User::factory()->create();
    $page = get(route('login'))->assertOk();
    Config::set('auth.enabled', false);

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page, [
        'email' => $user->email,
        'password' => 'password',
    ], [['method' => 'login', 'params' => []]]), ['X-Livewire' => 'true'])
        ->assertRedirectToRoute('home');

    assertGuest();
});

it('rejects a stale login form after the user has already authenticated', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $page = get(route('login'))->assertOk();
    Auth::login($user);

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page, [
        'email' => $otherUser->email,
        'password' => 'password',
    ], [['method' => 'login', 'params' => []]]), ['X-Livewire' => 'true'])
        ->assertRedirectToRoute('home');

    assertAuthenticatedAs($user);
});
