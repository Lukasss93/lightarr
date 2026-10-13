<?php

use App\Livewire\Dashboard;
use App\Models\Library;
use App\Models\LibraryPath;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows tracked metadata and calculates present bytes separately from other statuses', function (): void {
    $library = Library::factory()->create(['name' => 'Movies']);
    $root = LibraryPath::factory()->for($library)->atPath('/media/movies')->create();
    MediaFile::factory()->for($root, 'libraryPath')->atPath('Movies/Film.mkv')->create(['size_bytes' => 2147483648]);
    MediaFile::factory()->for($root, 'libraryPath')->missing()->create();
    MediaFile::factory()->for($root, 'libraryPath')->excluded()->create();

    actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertSee('Film.mkv')->assertSee('/media/movies');
    Livewire::test(Dashboard::class)->assertViewHas('counts', ['present' => 1, 'missing' => 1, 'excluded' => 1])
        ->assertViewHas('presentSize', '2 GB');
});

it('filters by filename, relative path, library, and availability', function (): void {
    $movies = Library::factory()->create();
    $shows = Library::factory()->create();
    $movieRoot = LibraryPath::factory()->for($movies)->create();
    $showRoot = LibraryPath::factory()->for($shows)->create();
    MediaFile::factory()->for($movieRoot, 'libraryPath')->atPath('Drama/My Film.mkv')->create();
    MediaFile::factory()->for($showRoot, 'libraryPath')->atPath('Series/Episode.mkv')->missing()->create();

    $component = Livewire::test(Dashboard::class);
    $component->set('search', 'My Film')->assertSee('My Film.mkv')->assertDontSee('Episode.mkv')
        ->set('search', 'Drama/')->assertSee('My Film.mkv')
        ->set('search', '')->set('library', (string) $shows->id)->assertSee('Episode.mkv')->assertDontSee('My Film.mkv')
        ->assertViewHas('counts', ['present' => 0, 'missing' => 1, 'excluded' => 0]);
    $component->set('library', '')->set('status', 'present')->assertSee('My Film.mkv')->assertDontSee('Episode.mkv');
});

it('matches wildcard characters in searches literally and ignores letter case', function (): void {
    $root = LibraryPath::factory()->create();

    foreach (['S01_E01.mkv', 'S01xE01.mkv', '100% Pure.mkv', '1000 Pure.mkv', 'Wow!.mkv', 'Wowza.mkv'] as $path) {
        MediaFile::factory()->for($root, 'libraryPath')->atPath($path)->create();
    }

    Livewire::test(Dashboard::class)
        ->set('search', 'S01_E01')->assertSee('S01_E01.mkv')->assertDontSee('S01xE01.mkv')
        ->set('search', '100%')->assertSee('100% Pure.mkv')->assertDontSee('1000 Pure.mkv')
        ->set('search', 'Wow!')->assertSee('Wow!.mkv')->assertDontSee('Wowza.mkv')
        ->set('search', 'pure')->assertSee('100% Pure.mkv')->assertSee('1000 Pure.mkv');
});

it('ignores library and availability filters that are not valid values', function (): void {
    $root = LibraryPath::factory()->create();
    MediaFile::factory()->for($root, 'libraryPath')->atPath('Movie.mkv')->create();

    Livewire::withQueryParams(['library' => 'abc', 'status' => 'unknown'])->test(Dashboard::class)
        ->assertOk()->assertSee('Movie.mkv')->assertViewHas('counts', ['present' => 1, 'missing' => 0, 'excluded' => 0]);
});

it('paginates deterministically and returns to page one when a filter changes', function (): void {
    $root = LibraryPath::factory()->create();

    for ($index = 0; $index < 26; $index++) {
        MediaFile::factory()->for($root, 'libraryPath')->atPath(sprintf('film-%02d.mkv', $index))->create();
    }

    $component = Livewire::test(Dashboard::class);
    $component->assertViewHas('files', fn (LengthAwarePaginator $files): bool => count($files->items()) === 25 && $files->total() === 26);
    $component->assertSee('film-00.mkv')->assertDontSee('film-25.mkv')
        ->call('setPage', 2)->assertSee('film-25.mkv')->assertDontSee('film-00.mkv')
        ->set('search', 'film-00')->assertSee('film-00.mkv')->assertSet('paginators.page', 1);
});

it('escapes filenames and library names in inventory output', function (): void {
    $library = Library::factory()->create(['name' => '<script>library()</script>']);
    $root = LibraryPath::factory()->for($library)->create();
    $filename = '<img src=x onerror=alert(1)>.mkv';
    MediaFile::factory()->for($root, 'libraryPath')->atPath($filename)->create();

    actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertSee($filename)->assertDontSee($filename, false)
        ->assertSee($library->name)->assertDontSee($library->name, false);
});

it('shows an empty inventory without requiring an account when authentication is disabled', function (): void {
    Config::set('auth.enabled', false);

    get(route('home'))->assertOk()->assertSee('Media inventory')->assertSee('No video files match these filters');
    get(route('settings.libraries'))->assertOk()->assertSee('Add your first library');
});
