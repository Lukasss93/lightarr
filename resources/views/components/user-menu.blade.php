@php
    $currentUser = config('auth.enabled') ? auth()->user() : null;
@endphp

<x-dropdown width="xl" position="bottom-end" x-on:select="show = false">
    <x-slot:action>
        <button type="button" aria-label="User menu" aria-haspopup="menu" aria-controls="user-menu-panel"
            x-ref="userMenuTrigger" x-bind:aria-expanded="show"
            x-on:click="show = !show"
            x-on:keydown.arrow-down.prevent="show = true; $nextTick(() => $refs.userMenuPanel?.querySelector('button')?.focus())"
            x-on:keydown.escape.stop.prevent="show = false"
            class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-gray-200 bg-white p-1.5 text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:border-dark-700 dark:bg-dark-900 dark:text-dark-200 dark:hover:bg-dark-800">
            <span class="grid size-8 place-items-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300">
                <x-icon icon="user-circle" class="size-6" aria-hidden="true" />
            </span>
            <span class="hidden max-w-32 truncate text-sm font-medium md:block">{{ $currentUser?->name ?? 'User menu' }}</span>
            <x-icon icon="chevron-down" class="mr-1 size-4" aria-hidden="true" />
        </button>
    </x-slot:action>

    <div id="user-menu-panel" x-ref="userMenuPanel"
        x-on:keydown.escape.stop.prevent="show = false; $refs.userMenuTrigger.focus()">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-dark-700">
            @if ($currentUser)
                <p class="break-all text-sm font-semibold text-gray-900 dark:text-white">{{ $currentUser->name }}</p>
                <p class="break-all text-xs text-gray-500 dark:text-dark-400">{{ $currentUser->email }}</p>
            @else
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Local access</p>
            @endif
        </div>

        <div class="space-y-3 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-dark-400">Appearance</p>
            <div role="group" aria-label="Color theme"
                x-effect="
                    const buttons = $el.querySelectorAll('button');
                    [['dark', 'Dark theme'], ['system', 'System theme'], ['light', 'Light theme']].forEach(([value, label], index) => {
                        buttons[index].setAttribute('aria-label', label);
                        buttons[index].setAttribute('aria-pressed', mode === value);
                    });
                ">
                <x-theme-switch />
            </div>
        </div>

        @if ($currentUser)
            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-dark-700">
                @csrf
                <x-dropdown.items text="Log out" icon="arrow-right-start-on-rectangle" type="submit" />
            </form>
        @endif
    </div>
</x-dropdown>
