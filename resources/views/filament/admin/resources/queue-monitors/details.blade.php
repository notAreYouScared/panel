<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Job ID</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->job_id }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Queue</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->queue ?? 'default' }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Started At</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->started_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Finished At</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->finished_at?->format('Y-m-d H:i:s') ?? 'Still running' }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Attempts</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->attempt }}</p>
        </div>

        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Progress</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->progress ?? 0 }}%</p>
        </div>
    </div>

    @if($record->failed && $record->exception_message)
        <div class="mt-4">
            <h3 class="text-sm font-medium text-red-700 dark:text-red-400">Exception Message</h3>
            <div class="mt-2 rounded-md bg-red-50 dark:bg-red-900/20 p-4">
                <pre class="text-xs text-red-900 dark:text-red-200 whitespace-pre-wrap break-words">{{ $record->exception_message }}</pre>
            </div>
        </div>
    @endif

    @if($record->finished_at)
        <div class="mt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Duration</h3>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $record->started_at?->diffForHumans($record->finished_at, true) ?? 'N/A' }}
            </p>
        </div>
    @endif
</div>
