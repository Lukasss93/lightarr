<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

use function Pest\Laravel\artisan;

pest()->extend(TestCase::class)->use(LazilyRefreshDatabase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $parameters
 */
function artisanCommand(string $command, array $parameters = []): PendingCommand
{
    $pendingCommand = artisan($command, $parameters);

    if (! $pendingCommand instanceof PendingCommand) {
        throw new LogicException('Console output must be mocked to assert command prompts and output.');
    }

    return $pendingCommand;
}

/**
 * @param  TestResponse<Response>  $response
 * @param  array<string, mixed>  $updates
 * @param  list<array{method: string, params: list<mixed>}>  $calls
 * @return array{components: list<array{snapshot: string, updates: array<string, mixed>, calls: list<array{method: string, params: list<mixed>}>}>}
 */
function livewireUpdatePayload(TestResponse $response, array $updates = [], array $calls = []): array
{
    $snapshot = str((string) $response->getContent())->betweenFirst('wire:snapshot="', '"');

    return [
        'components' => [[
            'snapshot' => htmlspecialchars_decode((string) $snapshot, ENT_QUOTES),
            'updates' => $updates,
            'calls' => $calls,
        ]],
    ];
}
