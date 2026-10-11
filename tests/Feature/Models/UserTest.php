<?php

use App\Models\User;

it('hides sensitive attributes when serializing a user', function (): void {
    $user = User::factory()->make([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $attributes = $user->toArray();

    expect(array_keys($attributes))->toBe(['name', 'email', 'email_verified_at'])
        ->and($attributes['name'] ?? null)->toBe('Test User')
        ->and($attributes['email'] ?? null)->toBe('test@example.com');
});
