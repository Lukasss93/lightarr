<?php

use App\Jobs\ScanLibraryPath;
use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Pest\TestSuite;
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

/**
 * @param  array<string, string>  $files
 */
function mediaDirectory(array $files = []): string
{
    $testCase = TestSuite::getInstance()->test;

    if (! $testCase instanceof TestCase) {
        throw new LogicException('Media fixtures require a Laravel test case.');
    }

    return $testCase->mediaDirectory($files);
}

/**
 * Run a scan of the folder through the synchronous queue, so failures are recorded as a worker would record them.
 *
 * @param  list<string>  $patterns
 */
function scanMediaPath(LibraryPath $path, array $patterns = []): void
{
    $token = (string) Str::uuid();
    $path->refresh()->update(['scan_status' => ScanStatus::Queued, 'scan_token' => $token, 'scan_requested_at' => now()]);
    $path->library()->firstOrFail()->update(['exclusion_patterns' => $patterns]);
    ScanLibraryPath::dispatchSync($path->id, $token);
}
