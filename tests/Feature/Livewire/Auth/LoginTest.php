<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

it('renders the login form for guests', function (): void {
    get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee(Config::string('app.name'))
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertDontSee('Log out')
        ->assertDontSee('<header', false);
});

it('redirects authenticated users away from login', function (): void {
    $user = User::factory()->create();

    actingAs($user)->get(route('login'))->assertRedirectToRoute('home');
});

it('normalizes the email and logs in with a new session', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $component = Livewire::test(Login::class)
        ->set('email', '  TEST@EXAMPLE.COM  ')
        ->set('password', 'password');
    $sessionId = session()->getId();

    $component->call('login')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('home')
        ->assertSet('password', '');

    assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
});

it('returns the user to the intended page after login', function (): void {
    $user = User::factory()->create();
    $intendedUrl = route('home').'?tab=jobs';
    session()->put('url.intended', $intendedUrl);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect($intendedUrl);

    assertAuthenticatedAs($user);
    expect(session()->has('url.intended'))->toBeFalse();
});

it('issues a remembered login cookie when requested', function (): void {
    $user = User::factory()->create();
    $page = get(route('login'))->assertOk();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page, [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ], [['method' => 'login', 'params' => []]]), ['X-Livewire' => 'true'])
        ->assertOk()
        ->assertJsonPath('components.0.effects.redirect', route('home'))
        ->assertCookie(Auth::getRecallerName());

    assertAuthenticatedAs($user);
});

it('keeps remembered login off by default', function (): void {
    $user = User::factory()->create();
    $page = get(route('login'))->assertOk();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page, [
        'email' => $user->email,
        'password' => 'password',
    ], [['method' => 'login', 'params' => []]]), ['X-Livewire' => 'true'])
        ->assertOk()
        ->assertCookieMissing(Auth::getRecallerName());

    assertAuthenticatedAs($user);
});

it('returns the same error for an unknown email and an incorrect password', function (string $email): void {
    User::factory()->create(['email' => 'test@example.com']);

    Livewire::test(Login::class)
        ->set('email', $email)
        ->set('password', 'incorrect-password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee(__('auth.failed'))
        ->assertSet('password', '');

    assertGuest();
})->with(['incorrect password' => 'test@example.com', 'unknown email' => 'unknown@example.com']);

it('validates login input without retaining the password', function (string $email, string $password, string $field, string $rule): void {
    Livewire::test(Login::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('login')
        ->assertHasErrors([$field => $rule])
        ->assertSet('password', '');

    assertGuest();
})->with([
    'missing email' => ['', 'password', 'email', 'required'],
    'invalid email' => ['invalid-email', 'password', 'email', 'email'],
    'long email' => [str_repeat('a', 245).'@example.com', 'password', 'email', 'max'],
    'missing password' => ['test@example.com', '', 'password', 'required'],
]);

it('blocks login after five failures until the limit expires', function (): void {
    freezeTime();
    $user = User::factory()->create();
    $component = Livewire::test(Login::class)->set('email', $user->email);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $component->set('password', 'incorrect-password')->call('login')->assertSee(__('auth.failed'));
    }

    $component->set('password', 'password')->call('login')
        ->assertHasErrors('email')
        ->assertSee(__('auth.throttle', ['seconds' => 60, 'minutes' => 1]));

    assertGuest();
    travel(61)->seconds();

    $component->set('password', 'password')->call('login')->assertRedirectToRoute('home');

    assertAuthenticatedAs($user);
});

it('clears failed attempts after successful login', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $component = Livewire::test(Login::class)->set('email', $user->email);
    $component->set('password', 'incorrect-password')->call('login')->assertHasErrors('email');

    $component->set('password', 'password')->call('login')->assertRedirectToRoute('home');

    expect(RateLimiter::attempts('test@example.com|127.0.0.1'))->toBe(0);
});

it('does not authenticate through the component when login is disabled', function (): void {
    $user = User::factory()->create();
    Config::set('auth.enabled', false);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirectToRoute('home')
        ->assertSet('password', '');

    assertGuest();
});

it('keeps the current account when an authenticated user calls login', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Livewire::actingAs($user)->test(Login::class)
        ->set('email', $otherUser->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirectToRoute('home');

    assertAuthenticatedAs($user);
});
