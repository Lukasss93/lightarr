<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        try {
            if (! Config::boolean('auth.enabled') || Auth::check()) {
                $this->redirectRoute('home');

                return;
            }

            $this->email = Str::lower(trim($this->email));

            $this->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string'],
                'remember' => ['boolean'],
            ]);

            $throttleKey = Str::transliterate($this->email.'|'.request()->ip());

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);

                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => (int) ceil($seconds / 60),
                    ]),
                ]);
            }

            if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            RateLimiter::clear($throttleKey);
            session()->regenerate();

            $this->redirectIntended(route('home'));
        } finally {
            $this->reset('password');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.login')->layoutData(['authenticationPage' => true]);
    }
}
