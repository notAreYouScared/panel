<?php

namespace App\Services\Servers;

use App\Enums\NodeJwtScope;
use App\Events\Server\StatsUpdated;
use App\Models\Server;
use App\Services\Nodes\NodeJWTService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class StatsCollectionService
{
    private ?WebSocket $connection = null;

    public function __construct(
        private readonly NodeJWTService $nodeJWTService,
    ) {}

    /**
     * Connect to the Wings websocket and start collecting stats for a server.
     *
     * @throws \Throwable
     */
    public function connect(Server $server): void
    {
        if ($this->isConnected($server)) {
            return;
        }

        try {
            $loop = Loop::get();
            $connector = new Connector($loop);
            $token = $this->generateToken($server);
            $socket = $this->getSocketUrl($server);

            $connector($socket)->then(
                fn (WebSocket $conn) => $this->onConnect($conn, $server, $token, $loop),
                fn (\Throwable $e) => $this->onError($server, $e, $loop)
            );

            $loop->run();
        } catch (\Throwable $e) {
            Log::error('Failed to connect to Wings websocket for server stats', [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $this->disconnect($server);
        }
    }

    /**
     * Handle successful websocket connection.
     */
    private function onConnect(WebSocket $conn, Server $server, string $token, LoopInterface $loop): void
    {
        $this->connection = $conn;
        $connectionKey = "server_stats_connection.{$server->id}";

        // Mark connection as active
        Cache::put($connectionKey, true, now()->addMinutes(5));

        // Send auth message
        $conn->send(json_encode([
            'event' => 'auth',
            'args' => [$token],
        ]));

        // Handle incoming messages
        $conn->on('message', fn ($msg) => $this->handleMessage($server, $msg));
        $conn->on('close', fn () => $loop->stop());
        $conn->on('error', fn (\Throwable $e) => $this->onError($server, $e, $loop));
    }

    /**
     * Handle incoming websocket messages from Wings.
     */
    private function handleMessage(Server $server, string $message): void
    {
        try {
            $data = json_decode($message, true);
            if ($data === null || $data['event'] !== 'stats') {
                return;
            }

            $stats = $data['args'][0] ?? null;
            if ($stats === null) {
                return;
            }

            // Cache the stats server-side
            $timestamp = now()->getTimestamp();
            $stats = is_string($stats) ? json_decode($stats, true) : $stats;

            foreach ((array) $stats as $key => $value) {
                $cacheKey = "servers.{$server->id}.$key";
                $cachedStats = cache()->get($cacheKey, []);

                $cachedStats[$timestamp] = $value;

                cache()->put($cacheKey, array_slice($cachedStats, -120), now()->addMinute());
            }

            // Broadcast the stats to all connected clients
            broadcast(new StatsUpdated($server, $stats));
        } catch (\Throwable $e) {
            Log::error('Error processing websocket message', [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle websocket connection error.
     */
    private function onError(Server $server, \Throwable $e, LoopInterface $loop): void
    {
        Log::error('Websocket connection error for server stats', [
            'server_id' => $server->id,
            'exception' => $e,
        ]);

        $loop->stop();
    }

    /**
     * Check if a websocket connection is already active for a server.
     */
    private function isConnected(Server $server): bool
    {
        $connectionKey = "server_stats_connection.{$server->id}";

        return Cache::has($connectionKey) && $this->connection !== null;
    }

    /**
     * Disconnect from the websocket and clear the connection cache.
     */
    private function disconnect(Server $server): void
    {
        try {
            if ($this->connection !== null) {
                $this->connection->close();
            }
        } catch (\Throwable $e) {
            Log::debug('Error closing websocket connection', ['exception' => $e]);
        }

        Cache::forget("server_stats_connection.{$server->id}");
    }

    /**
     * Generate a JWT token for Wings websocket authentication.
     */
    private function generateToken(Server $server): string
    {
        return $this->nodeJWTService
            ->setExpiresAt(now()->addMinutes(10)->toImmutable())
            ->setScopes(NodeJwtScope::Websocket)
            ->setClaims([
                'server_uuid' => $server->uuid,
            ])
            ->handle($server->node, $server->uuid)
            ->toString();
    }

    /**
     * Get the websocket URL for the server's node.
     */
    private function getSocketUrl(Server $server): string
    {
        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $server->node->getConnectionAddress());

        return $socket . sprintf('/api/servers/%s/ws', $server->uuid);
    }
}
