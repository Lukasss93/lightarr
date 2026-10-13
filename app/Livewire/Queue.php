<?php

namespace App\Livewire;

use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Queue')]
class Queue extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    private Factory $queues;

    public function boot(Factory $queues): void
    {
        $this->queues = $queues;
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $connection = $this->queues->connection();
        $statistics = [
            'waiting' => $connection->pendingSize(),
            'processing' => $connection->reservedSize(),
            'delayed' => $connection->delayedSize(),
            'failed' => LibraryPath::query()->where('scan_status', ScanStatus::Failed)->count(),
        ];
        $activity = LibraryPath::query()->with('library')
            ->where('scan_status', '!=', ScanStatus::Idle)
            ->when($this->status !== '', fn ($query) => $query->where('scan_status', $this->status))
            ->orderByDesc('scan_requested_at')->orderByDesc('id')->paginate(25);

        return view('livewire.queue', [
            'statistics' => $statistics,
            'activity' => $activity,
            'statusOptions' => [
                ['label' => 'All scan activity', 'value' => ''],
                ['label' => 'Queued', 'value' => ScanStatus::Queued->value],
                ['label' => 'Scanning', 'value' => ScanStatus::Scanning->value],
                ['label' => 'Completed', 'value' => ScanStatus::Completed->value],
                ['label' => 'Failed', 'value' => ScanStatus::Failed->value],
            ],
        ]);
    }
}
