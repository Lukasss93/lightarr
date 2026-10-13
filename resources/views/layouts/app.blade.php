@props(['authenticationPage' => false, 'settingsPage' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

        @fonts
        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-dark-950 dark:text-gray-100"
        x-bind:class="{ 'dark': darkTheme }"
    >
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4 dark:focus:bg-dark-900">
            Skip to main content
        </a>

        @unless ($authenticationPage)
            <header class="sticky top-0 z-30 border-b border-gray-200 bg-white dark:border-dark-700 dark:bg-dark-900">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" wire:navigate class="mr-auto inline-flex items-center gap-3 text-base font-semibold tracking-tight sm:mr-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-primary-600 text-white">
                            <x-icon icon="film" class="size-5" aria-hidden="true" />
                        </span>
                        {{ config('app.name') }}
                    </a>

                    <nav aria-label="Main navigation" class="order-last flex w-full items-center gap-1.5 border-t border-gray-100 pt-3 sm:order-none sm:w-auto sm:border-0 sm:pt-0 dark:border-dark-700">
                        <x-navigation-link :href="route('home')" icon="squares-2x2"
                            :active="request()->routeIs('home')" class="flex-1 sm:flex-none">
                            Dashboard
                        </x-navigation-link>
                        <x-navigation-link :href="route('queue.index')" icon="queue-list"
                            :active="request()->routeIs('queue.index')" class="flex-1 sm:flex-none">
                            Queue
                        </x-navigation-link>
                        <x-navigation-link :href="route('settings.index')" icon="cog-6-tooth"
                            :active="request()->routeIs('settings.*')" class="flex-1 sm:flex-none">
                            Settings
                        </x-navigation-link>
                    </nav>

                    <div class="shrink-0 sm:ml-auto">
                        <x-user-menu />
                    </div>
                </div>
            </header>
        @endunless

        <main id="main-content" @class([
            'px-4 sm:px-6 lg:px-8',
            'flex min-h-dvh items-center justify-center py-12' => $authenticationPage,
            'mx-auto max-w-7xl py-8' => ! $authenticationPage,
        ])>
            @if ($settingsPage)
                <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-8">
                    <x-settings-sidebar />
                    <section aria-label="Settings content" class="min-w-0">
                        {{ $slot }}
                    </section>
                </div>
            @else
                {{ $slot }}
            @endif
        </main>

        <x-dialog />
        <x-toast />

        @livewireScripts
    </body>
</html>
