<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Services\Servers\StatsCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectServerStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public function __construct(public Server $server) {}

    /**
     * Execute the job - maintain a websocket connection and collect stats.
     */
    public function handle(StatsCollectionService $statsCollectionService): void
    {
        $connectionKey = "server_stats_connection.{$this->server->id}";

        // Mark this connection as active with a 5-minute timeout
        Cache::put($connectionKey, true, now()->addMinutes(5));

        try {
            $statsCollectionService->connect($this->server);
        } catch (\Throwable $e) {
            Log::error('Stats collection job failed for server', [
                'server_id' => $this->server->id,
                'exception' => $e,
            ]);
        }

        // Cleanup
        Cache::forget($connectionKey);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ["server:{$this->server->id}"];
    }
}
