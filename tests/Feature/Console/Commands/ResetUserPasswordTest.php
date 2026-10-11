<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withCookie;

it('resets a normalized email account and rotates its remember token', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $rememberToken = $user->remember_token;
    Event::fake([PasswordReset::class]);

    artisanCommand('user:reset-password', ['email' => '  TEST@EXAMPLE.COM  '])
        ->expectsQuestion('Password', 'replacement-password')
        ->expectsQuestion('Confirm password', 'replacement-password')
        ->expectsOutput('Password reset successfully.')
        ->doesntExpectOutput('replacement-password')
        ->assertSuccessful();

    $user->refresh();
    expect(Hash::check('replacement-password', $user->password))->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
    expect($user->remember_token)->not->toBe($rememberToken);
    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
});

it('rejects a missing account without creating one', function (): void {
    artisanCommand('user:reset-password', ['email' => 'missing@example.com'])
        ->expectsOutput('No user exists with that email address.')
        ->assertFailed();

    assertDatabaseCount('users', 0);
});

it('rejects an invalid replacement password without changing the account', function (string $password, string $confirmation, string $error): void {
    $user = User::factory()->create();
    $passwordHash = $user->password;
    $rememberToken = $user->remember_token;
    Event::fake([PasswordReset::class]);

    artisanCommand('user:reset-password', ['email' => $user->email])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->expectsOutput($error)
        ->assertFailed();

    $user->refresh();
    expect($user->password)->toBe($passwordHash);
    expect($user->remember_token)->toBe($rememberToken);
    Event::assertNotDispatched(PasswordReset::class);
})->with([
    'missing password' => ['', '', 'The password field is required.'],
    'short password' => ['short', 'short', 'The password field must be at least 8 characters.'],
    'mismatched passwords' => ['replacement-password', 'different-password', 'The password field confirmation does not match.'],
]);

it('fails without changing data when interactive input is unavailable', function (): void {
    $user = User::factory()->create();
    $passwordHash = $user->password;

    artisanCommand('user:reset-password', ['email' => $user->email, '--no-interaction' => true])
        ->expectsOutput('This command requires interactive input.')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($passwordHash);
});

it('revokes an existing page session after password reset', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    get(route('home'))->assertOk();

    artisanCommand('user:reset-password', ['email' => $user->email])
        ->expectsQuestion('Password', 'replacement-password')
        ->expectsQuestion('Confirm password', 'replacement-password')
        ->assertSuccessful();
    Auth::forgetGuards();

    get(route('home'))->assertRedirectToRoute('login');

    assertGuest();
});

it('revokes a login session even before the redirected page is opened', function (): void {
    $user = User::factory()->create();
    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirectToRoute('home');

    artisanCommand('user:reset-password', ['email' => $user->email])
        ->expectsQuestion('Password', 'replacement-password')
        ->expectsQuestion('Confirm password', 'replacement-password')
        ->assertSuccessful();
    Auth::forgetGuards();

    get(route('home'))->assertRedirectToRoute('login');

    assertGuest();
});

it('returns 401 for an existing Livewire session after password reset', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    $page = get(route('home'))->assertOk();

    artisanCommand('user:reset-password', ['email' => $user->email])
        ->expectsQuestion('Password', 'replacement-password')
        ->expectsQuestion('Confirm password', 'replacement-password')
        ->assertSuccessful();
    Auth::forgetGuards();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])
        ->assertUnauthorized();

    assertGuest();
});

it('rejects the old remembered login cookie after password reset', function (): void {
    $user = User::factory()->create();
    Auth::login($user, true);
    $page = get(route('home'))->assertOk();
    $cookieValue = $page->getCookie(Auth::getRecallerName())?->getValue() ?? throw new LogicException('Expected a remembered login cookie.');

    artisanCommand('user:reset-password', ['email' => $user->email])
        ->expectsQuestion('Password', 'replacement-password')
        ->expectsQuestion('Confirm password', 'replacement-password')
        ->assertSuccessful();
    session()->invalidate();
    Auth::forgetGuards();

    withCookie(Auth::getRecallerName(), $cookieValue)
        ->get(route('home'))
        ->assertRedirectToRoute('login');

    assertGuest();
});
