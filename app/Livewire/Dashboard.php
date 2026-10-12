<?php

namespace App\Livewire;

use App\Models\Library;
use App\Models\MediaFile;
use App\Scan\Enums\MediaFileStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $library = '';

    #[Url]
    public string $status = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'library', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $query = $this->inventory();
        $counts = [];

        foreach (MediaFileStatus::cases() as $status) {
            $counts[$status->value] = (clone $query)->where('status', $status)->count();
        }

        return view('livewire.dashboard', [
            'files' => (clone $query)->with('libraryPath.library')->orderBy('relative_path')->orderBy('id')->paginate(25),
            'counts' => $counts,
            'presentSize' => number_format((float) (clone $query)->where('status', MediaFileStatus::Present)->sum('size_bytes') / 1073741824, 2).' GiB',
            'libraryOptions' => [
                ['label' => 'All libraries', 'value' => ''],
                ...Library::query()->orderBy('name')->orderBy('id')->get()
                    ->map(fn (Library $library): array => ['label' => $library->name, 'value' => (string) $library->id])->all(),
            ],
            'statusOptions' => [
                ['label' => 'All statuses', 'value' => ''],
                ...array_map(fn (MediaFileStatus $status): array => ['label' => ucfirst($status->value), 'value' => $status->value], MediaFileStatus::cases()),
            ],
        ]);
    }

    /** @return Builder<MediaFile> */
    private function inventory(): Builder
    {
        return MediaFile::query()
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('relative_path', '%'.$this->search.'%'))
            ->when($this->library !== '', fn (Builder $query) => $query->whereHas('libraryPath', fn (Builder $paths) => $paths->where('library_id', $this->library)))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status));
    }
}
