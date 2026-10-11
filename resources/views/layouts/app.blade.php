@props(['authenticationPage' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @fonts
        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-dark-950 dark:text-gray-100"
        x-bind:class="{ 'dark': darkTheme }"
    >
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:p-4">
            Skip to main content
        </a>

        @unless ($authenticationPage)
            <header class="border-b border-gray-200 bg-white dark:border-dark-700 dark:bg-dark-900">
                <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                    <a href="{{ route('home') }}" wire:navigate class="text-lg font-semibold tracking-tight">
                        {{ config('app.name') }}
                    </a>

                    <div class="flex flex-wrap items-center gap-4">
                        <div
                            role="group"
                            aria-label="Color theme"
                            x-effect="
                                const buttons = $el.querySelectorAll('button');
                                [['dark', 'Dark theme'], ['system', 'System theme'], ['light', 'Light theme']].forEach(([value, label], index) => {
                                    buttons[index].setAttribute('aria-label', label);
                                    buttons[index].setAttribute('aria-pressed', mode === value);
                                });
                            "
                        >
                            <x-theme-switch />
                        </div>

                        @if (config('auth.enabled'))
                            @auth
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ auth()->user()?->name }}</span>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-button type="submit" text="Log out" sm flat />
                                    </form>
                                </div>
                            @endauth
                        @endif
                    </div>
                </div>
            </header>
        @endunless

        <main id="main-content" @class([
            'px-6 py-12',
            'flex min-h-dvh items-center justify-center' => $authenticationPage,
            'mx-auto max-w-5xl' => ! $authenticationPage,
        ])>
            {{ $slot }}
        </main>

        <x-dialog />
        <x-toast />

        @livewireScripts
    </body>
</html>
