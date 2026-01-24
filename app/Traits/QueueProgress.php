<?php

namespace App\Traits;

use App\Models\QueueMonitor;

trait QueueProgress
{
    /**
     * Update job progress.
     *
     * @param int $progress Progress percentage (0-100)
     */
    public function setProgress(int $progress): void
    {
        $progress = min(100, max(0, $progress));

        if (! $monitor = $this->getQueueMonitor()) {
            return;
        }

        $monitor->update([
            'progress' => $progress,
        ]);
    }

    /**
     * Get the QueueMonitor model for this job.
     */
    protected function getQueueMonitor(): ?QueueMonitor
    {
        if (! property_exists($this, 'job')) {
            return null;
        }

        if (! $this->job) {
            return null;
        }

        if (! $jobId = QueueMonitor::getJobId($this->job)) {
            return null;
        }

        return QueueMonitor::whereJobId($jobId)
            ->orderBy('started_at', 'desc')
            ->first();
    }
}
