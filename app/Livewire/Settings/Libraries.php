<?php

namespace App\Livewire\Settings;

use App\Models\Library;
use App\Models\LibraryPath;
use App\Scan\Actions\RequestLibraryScan;
use App\Scan\Actions\SaveLibrary;
use App\Scan\DirectoryBrowser;
use App\Scan\LibraryPathResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Library settings')]
class Libraries extends Component
{
    public bool $showEditor = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $automaticScanning = true;

    public string $interval = '15';

    public string $intervalUnit = 'minutes';

    public string $exclusions = '';

    /** @var list<string> */
    public array $paths = [];

    public string $newPath = '';

    public bool $showBrowser = false;

    public string $browserPath = '/';

    #[Locked]
    public string $currentDirectory = '/';

    #[Locked]
    public int $browserPage = 1;

    public string $notice = '';

    private DirectoryBrowser $browser;

    public function boot(DirectoryBrowser $browser): void
    {
        $this->browser = $browser;
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'automaticScanning', 'interval', 'intervalUnit', 'exclusions', 'paths', 'newPath', 'showBrowser');
        $this->resetValidation();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $library = Library::query()->with('paths')->findOrFail($id);
        $this->create();
        $this->editingId = $library->id;
        $this->name = $library->name;
        $this->automaticScanning = $library->automatic_scanning;
        $minutes = $library->scan_interval_minutes;
        $this->intervalUnit = $minutes % 1440 === 0 ? 'days' : ($minutes % 60 === 0 ? 'hours' : 'minutes');
        $this->interval = (string) ($minutes / match ($this->intervalUnit) {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        });
        $this->exclusions = implode("\n", $library->exclusion_patterns);
        $this->paths = array_values($library->paths->map(fn (LibraryPath $path): string => $path->path)->all());
    }

    public function addPath(): void
    {
        $this->validate(['newPath' => ['required', 'string', 'max:4096']]);
        $this->paths[] = trim($this->newPath);
        $this->newPath = '';
    }

    public function removePath(int $index): void
    {
        $paths = $this->paths;
        unset($paths[$index]);
        $this->paths = array_values($paths);
    }

    public function save(SaveLibrary $saveLibrary): void
    {
        if (trim($this->newPath) !== '') {
            $this->addPath();
        }

        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'automaticScanning' => ['boolean'],
            'interval' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'intervalUnit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
            'exclusions' => ['string', 'max:16384'],
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['required', 'string', 'max:4096'],
        ]);
        $minutes = (int) $this->interval * match ($this->intervalUnit) {
            'days' => 1440,
            'hours' => 60,
            default => 1,
        };

        if ($minutes > 2147483647) {
            throw ValidationException::withMessages(['interval' => 'The scan interval is too large.']);
        }

        $patterns = array_values(array_unique(array_filter(
            array_map(trim(...), explode("\n", $this->exclusions)),
            fn (string $pattern): bool => $pattern !== '',
        )));
        $saveLibrary->handle($this->editingId, [
            'name' => $this->name,
            'automatic_scanning' => $this->automaticScanning,
            'scan_interval_minutes' => $minutes,
            'exclusion_patterns' => $patterns,
        ], $this->paths);
        $this->showEditor = false;
        $this->showBrowser = false;
        $this->notice = 'Library saved.';
    }

    public function delete(int $id): void
    {
        Library::query()->findOrFail($id)->delete();
        $this->notice = 'Library removed. Source files were left unchanged.';
    }

    public function scan(int $id, RequestLibraryScan $dispatcher): void
    {
        $count = $dispatcher->handle(Library::query()->findOrFail($id));
        $this->notice = $count === 0 ? 'All folders are already queued or scanning.' : 'Queued '.$count.' folder scans.';
    }

    public function browse(): void
    {
        $this->navigate('/');
        $this->showBrowser = true;
    }

    public function navigate(?string $path = null, int $page = 1): void
    {
        $listing = $this->browser->browse($path ?? $this->browserPath, $page);
        $this->currentDirectory = $listing['path'];
        $this->browserPath = $listing['path'];
        $this->browserPage = $listing['page'];
        $this->resetValidation('browserPath');
    }

    public function selectDirectory(LibraryPathResolver $paths): void
    {
        $this->paths[] = $paths->canonical($this->currentDirectory, 'browserPath');
        $this->showBrowser = false;
    }

    public function render(): View
    {
        $listing = null;

        if ($this->showBrowser) {
            try {
                $listing = $this->browser->browse($this->currentDirectory, $this->browserPage);
            } catch (ValidationException $exception) {
                $this->addError('browserPath', $exception->validator->errors()->first());
            }
        }

        return view('livewire.settings.libraries', [
            'libraries' => Library::query()->with('paths')->orderBy('name')->orderBy('id')->get(),
            'listing' => $listing,
        ])->layoutData(['settingsPage' => true]);
    }
}
