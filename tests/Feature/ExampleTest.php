<?php

use App\Livewire\Home;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('returns a successful response for the home page', function (): void {
    actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Welcome to Rencoderr')
        ->assertSeeLivewire(Home::class);
});
