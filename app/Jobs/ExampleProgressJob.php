<?php

namespace App\Jobs;

use App\Traits\QueueProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Example job demonstrating queue monitoring with progress tracking.
 *
 * This job uses the QueueProgress trait to update its progress as it runs.
 * Usage:
 * ```php
 * ExampleProgressJob::dispatch($items);
 * ```
 */
class ExampleProgressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use QueueProgress;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $totalSteps = 5,
        protected int $delaySeconds = 2,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('ExampleProgressJob started', [
            'total_steps' => $this->totalSteps,
            'delay' => $this->delaySeconds,
        ]);

        // Initial progress
        $this->setProgress(0);

        // Simulate work with progress updates
        for ($i = 1; $i <= $this->totalSteps; $i++) {
            sleep($this->delaySeconds);

            $progress = (int) (($i / $this->totalSteps) * 100);
            $this->setProgress($progress);

            Log::info("ExampleProgressJob progress: {$progress}%");
        }

        Log::info('ExampleProgressJob completed');
    }
}
