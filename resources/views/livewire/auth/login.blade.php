<div class="w-full max-w-md">
    <x-card>
        <form wire:submit="login" class="space-y-6 py-4">
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">Enter your email and password to continue.</p>
            </div>

            <x-input wire:model="email" type="email" label="Email" autocomplete="username" autofocus required />
            <x-input wire:model="password" type="password" label="Password" autocomplete="current-password" required />
            <x-checkbox wire:model="remember" label="Remember me" />
            <x-button type="submit" text="Log in" loading="login" block />
        </form>
    </x-card>
</div>
