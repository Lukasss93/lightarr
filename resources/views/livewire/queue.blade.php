<div class="space-y-8" wire:poll.5s>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">Queue</h1>
            <p class="text-gray-500 dark:text-dark-400">Background jobs and the latest scans of your library folders.</p>
        </div>
        <x-badge :text="$statistics['processing'] > 0 ? 'Processing' : ($statistics['waiting'] > 0 ? 'Work waiting' : ($statistics['delayed'] > 0 ? 'Work scheduled' : 'Queue empty'))"
            :color="$statistics['processing'] > 0 ? 'blue' : 'gray'" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'waiting' => ['label' => 'Waiting jobs', 'icon' => 'queue-list'],
            'processing' => ['label' => 'Processing jobs', 'icon' => 'arrow-path'],
            'delayed' => ['label' => 'Scheduled jobs', 'icon' => 'clock'],
            'failed' => ['label' => 'Failed scans', 'icon' => 'exclamation-triangle'],
        ] as $key => $statistic)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-500 dark:text-dark-400">{{ $statistic['label'] }}</p>
                        <p class="text-3xl font-semibold tracking-tight">{{ number_format($statistics[$key]) }}</p>
                    </div>
                    <span class="rounded-lg bg-gray-50 p-2 text-gray-400 dark:bg-dark-900 dark:text-dark-400">
                        <x-icon :icon="$statistic['icon']" class="size-5" aria-hidden="true" />
                    </span>
                </div>
            </x-card>
        @endforeach
    </div>

    <section class="space-y-4" aria-labelledby="scan-activity-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h2 id="scan-activity-heading" class="text-lg font-semibold">Latest library scans</h2>
                <p class="text-sm text-gray-500 dark:text-dark-400">Each folder shows its most recent scan request.</p>
            </div>
            <div class="w-full sm:w-56">
                <x-select.native label="Scan status" wire:model.live="status" :options="$statusOptions" />
            </div>
        </div>

        <x-table :headers="[
            ['index' => 'path', 'label' => 'Library / folder'],
            ['index' => 'scan_status', 'label' => 'Status'],
            ['index' => 'scan_requested_at', 'label' => 'Requested (UTC)'],
            ['index' => 'scan_started_at', 'label' => 'Started (UTC)'],
            ['index' => 'scan_error', 'label' => 'Result'],
        ]" :rows="$activity" paginate>
            @interact('column_path', $scan)
                <div class="max-w-sm whitespace-normal">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $scan->library->name }}</p>
                    <p class="break-all font-mono text-xs">{{ $scan->path }}</p>
                </div>
            @endinteract
            @interact('column_scan_status', $scan)
                <x-badge :text="ucfirst($scan->scan_status->value)" :color="match ($scan->scan_status) {
                    \App\Scan\Enums\ScanStatus::Queued => 'amber',
                    \App\Scan\Enums\ScanStatus::Scanning => 'blue',
                    \App\Scan\Enums\ScanStatus::Completed => 'green',
                    \App\Scan\Enums\ScanStatus::Failed => 'red',
                    \App\Scan\Enums\ScanStatus::Idle => 'gray',
                }" />
            @endinteract
            @interact('column_scan_requested_at', $scan)
                {{ $scan->scan_requested_at?->format('Y-m-d H:i') ?? '—' }}
            @endinteract
            @interact('column_scan_started_at', $scan)
                {{ $scan->scan_started_at?->format('Y-m-d H:i') ?? '—' }}
            @endinteract
            @interact('column_scan_error', $scan)
                <p class="max-w-xs whitespace-normal break-all">
                    {{ $scan->scan_error ?? ($scan->scan_status === \App\Scan\Enums\ScanStatus::Completed ? 'Scan completed' : '—') }}
                </p>
            @endinteract
            <x-slot:empty>No scans match this status. Start a scan from the library settings.</x-slot:empty>
        </x-table>
    </section>
</div>
