<?php

use App\Jobs\ScanLibraryPath;
use App\Livewire\Queue;
use App\Models\Library;
use App\Models\LibraryPath;
use App\Models\User;
use App\Scan\Enums\ScanStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue as JobQueue;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

it('shows real waiting processing and scheduled job counts with the latest scan results', function (): void {
    Config::set('queue.default', 'database');
    freezeTime();
    $library = Library::factory()->create(['name' => 'Movies']);
    $running = LibraryPath::factory()->for($library)->atPath('/media/running')->create([
        'scan_status' => ScanStatus::Scanning, 'scan_requested_at' => now(), 'scan_started_at' => now(),
    ]);
    $waiting = LibraryPath::factory()->for($library)->atPath('/media/waiting')->create([
        'scan_status' => ScanStatus::Queued, 'scan_requested_at' => now(),
    ]);
    LibraryPath::factory()->for($library)->atPath('/media/failed')->create([
        'scan_status' => ScanStatus::Failed, 'scan_requested_at' => now(), 'scan_error' => 'Storage unavailable.',
    ]);
    LibraryPath::factory()->for($library)->atPath('/media/not-scanned')->create();
    JobQueue::push(new ScanLibraryPath($running->id, (string) Str::uuid()));
    expect(JobQueue::pop())->not->toBeNull();
    JobQueue::push(new ScanLibraryPath($waiting->id, (string) Str::uuid()));
    JobQueue::later(now()->addMinutes(10), new ScanLibraryPath($waiting->id, (string) Str::uuid()));
    JobQueue::pushOn('other-queue', new ScanLibraryPath($waiting->id, (string) Str::uuid()));

    Livewire::test(Queue::class)->assertViewHas('statistics', [
        'waiting' => 1, 'processing' => 1, 'delayed' => 1, 'failed' => 1,
    ])->assertSee('Movies')->assertSee('/media/running')->assertSee('/media/waiting')
        ->assertSee('Storage unavailable.')->assertDontSee('/media/not-scanned');
    actingAs(User::factory()->create())->get(route('queue.index'))->assertOk()->assertSeeLivewire(Queue::class);
});

it('refreshes queue counts and scan results after work finishes', function (): void {
    Config::set('queue.default', 'database');
    $root = LibraryPath::factory()->create(['scan_status' => ScanStatus::Scanning, 'scan_requested_at' => now()]);
    JobQueue::push(new ScanLibraryPath($root->id, (string) Str::uuid()));
    $job = JobQueue::pop();

    if ($job === null) {
        throw new LogicException('The processing fixture must be reserved.');
    }

    $component = Livewire::test(Queue::class);
    $component->assertViewHas('statistics', ['waiting' => 0, 'processing' => 1, 'delayed' => 0, 'failed' => 0]);

    $job->delete();
    $root->update(['scan_status' => ScanStatus::Completed, 'scan_finished_at' => now()]);
    $component->call('$refresh')->assertSee('Queue empty')->assertSee('Scan completed')
        ->assertViewHas('statistics', ['waiting' => 0, 'processing' => 0, 'delayed' => 0, 'failed' => 0]);
});

it('filters scan activity by its latest status', function (): void {
    LibraryPath::factory()->atPath('/media/pending')->create(['scan_status' => ScanStatus::Queued]);
    LibraryPath::factory()->atPath('/media/completed')->create(['scan_status' => ScanStatus::Completed]);
    LibraryPath::factory()->atPath('/media/failed')->create(['scan_status' => ScanStatus::Failed]);

    Livewire::test(Queue::class)->set('status', 'failed')->assertSee('/media/failed')->assertDontSee('/media/pending')
        ->assertDontSee('/media/completed')->set('status', 'completed')->assertSee('/media/completed')->assertDontSee('/media/failed');
});

it('paginates scan activity and resets the page when its filter changes', function (): void {
    $library = Library::factory()->create();

    for ($index = 0; $index < 26; $index++) {
        LibraryPath::factory()->for($library)->atPath('/media/folder-'.$index)->create([
            'scan_status' => $index === 0 ? ScanStatus::Failed : ScanStatus::Completed,
            'scan_requested_at' => now(),
        ]);
    }

    $component = Livewire::test(Queue::class);
    $component->assertViewHas('activity', fn (LengthAwarePaginator $activity): bool => count($activity->items()) === 25 && $activity->total() === 26);
    $component->call('setPage', 2)->assertSee('/media/folder-0')->set('status', 'failed')
        ->assertSet('paginators.page', 1)->assertSee('/media/folder-0')->assertDontSee('/media/folder-25');
});

it('escapes library names folder paths and scan errors in the queue page', function (): void {
    $name = '<script>library()</script>';
    $path = '/media/<img src=x onerror=alert(1)>';
    $error = '<script>error()</script>';
    LibraryPath::factory()->for(Library::factory()->create(['name' => $name]))->atPath($path)->create([
        'scan_status' => ScanStatus::Failed, 'scan_error' => $error,
    ]);

    actingAs(User::factory()->create())->get(route('queue.index'))->assertOk()
        ->assertSee($name)->assertDontSee($name, false)->assertSee($path)->assertDontSee($path, false)
        ->assertSee($error)->assertDontSee($error, false);
});

it('shows an empty queue when no work has been requested', function (): void {
    Config::set('auth.enabled', false);

    get(route('queue.index'))->assertOk()->assertSee('Queue empty')->assertSee('No scans match this status.');
});

it('requires authentication for live queue updates after the session expires', function (): void {
    Auth::login(User::factory()->create());
    $page = get(route('queue.index'))->assertOk();
    Auth::logout();
    session()->invalidate();
    Auth::forgetGuards();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])->assertUnauthorized();
});

it('allows queue updates without an account when authentication is disabled', function (): void {
    Config::set('auth.enabled', false);
    $page = get(route('queue.index'))->assertOk();

    postJson(Livewire::getUpdateUri(), livewireUpdatePayload($page), ['X-Livewire' => 'true'])->assertOk()
        ->assertJsonStructure(['components' => [['effects' => ['html']]]]);
});
