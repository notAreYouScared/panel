<?php

namespace App\Services\Servers\Sharing;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ServerConfigCreatorService
{
    public function fromFile(UploadedFile $file): Server
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidFileUploadException('The selected file was not uploaded successfully');
        }

        try {
            $parsed = Yaml::parse($file->getContent());
        } catch (\Exception $exception) {
            throw new InvalidFileUploadException('Could not parse YAML file: ' . $exception->getMessage());
        }

        return $this->createServer($parsed);
    }

    protected function createServer(array $config): Server
    {
        // Validate egg UUID exists
        $eggUuid = Arr::get($config, 'egg.uuid');
        $eggName = Arr::get($config, 'egg.name');
        
        if (!$eggUuid) {
            throw new InvalidFileUploadException('Egg UUID is required in the configuration file');
        }

        $egg = Egg::where('uuid', $eggUuid)->first();
        
        if (!$egg) {
            throw new InvalidFileUploadException(
                "Egg with UUID '{$eggUuid}'" . 
                ($eggName ? " (name: {$eggName})" : "") . 
                " does not exist in the system"
            );
        }

        // Get the first accessible node
        $node = Node::whereIn('id', user()?->accessibleNodes()->pluck('id'))->first();
        
        if (!$node) {
            throw new InvalidFileUploadException('No accessible nodes found');
        }

        // Get or create allocations
        $allocations = Arr::get($config, 'allocations', []);
        $primaryAllocation = null;
        
        if (!empty($allocations)) {
            foreach ($allocations as $allocationData) {
                $ip = Arr::get($allocationData, 'ip');
                $port = Arr::get($allocationData, 'port');
                $isPrimary = Arr::get($allocationData, 'is_primary', false);

                // Check if allocation exists and is available
                $allocation = Allocation::where('node_id', $node->id)
                    ->where('ip', $ip)
                    ->where('port', $port)
                    ->whereNull('server_id')
                    ->first();

                // If allocation doesn't exist, create it
                if (!$allocation) {
                    // Check if port is in use
                    $existingAllocation = Allocation::where('node_id', $node->id)
                        ->where('ip', $ip)
                        ->where('port', $port)
                        ->first();

                    if ($existingAllocation) {
                        // Find next available port
                        $port = $this->findNextAvailablePort($node->id, $ip, $port);
                    }

                    $allocation = Allocation::create([
                        'node_id' => $node->id,
                        'ip' => $ip,
                        'port' => $port,
                    ]);
                }

                // Set as primary allocation if specified
                if ($isPrimary && !$primaryAllocation) {
                    $primaryAllocation = $allocation;
                }
            }
        }

        // If no primary allocation specified, get any available allocation
        if (!$primaryAllocation) {
            $primaryAllocation = Allocation::where('node_id', $node->id)
                ->whereNull('server_id')
                ->first();
            
            if (!$primaryAllocation) {
                throw new InvalidFileUploadException('No available allocations found on node');
            }
        }

        // Use the current authenticated user as the owner
        $owner = user();
        
        if (!$owner) {
            throw new InvalidFileUploadException('No authenticated user found');
        }

        // Create the server
        $serverName = Arr::get($config, 'name', 'Imported Server');
        
        $server = Server::create([
            'uuid' => Str::uuid()->toString(),
            'uuid_short' => Str::uuid()->toString(),
            'name' => $serverName,
            'description' => Arr::get($config, 'description', ''),
            'owner_id' => $owner->id,
            'node_id' => $node->id,
            'allocation_id' => $primaryAllocation->id,
            'egg_id' => $egg->id,
            'startup' => Arr::get($config, 'settings.startup', $egg->startup_commands[0] ?? ''),
            'image' => Arr::get($config, 'settings.image', array_values($egg->docker_images)[0] ?? ''),
            'skip_scripts' => Arr::get($config, 'settings.skip_scripts', false),
            'memory' => Arr::get($config, 'limits.memory', 512),
            'swap' => Arr::get($config, 'limits.swap', 0),
            'disk' => Arr::get($config, 'limits.disk', 1024),
            'io' => Arr::get($config, 'limits.io', 500),
            'cpu' => Arr::get($config, 'limits.cpu', 0),
            'threads' => Arr::get($config, 'limits.threads'),
            'oom_killer' => Arr::get($config, 'limits.oom_killer', false),
            'database_limit' => Arr::get($config, 'feature_limits.databases', 0),
            'allocation_limit' => Arr::get($config, 'feature_limits.allocations', 0),
            'backup_limit' => Arr::get($config, 'feature_limits.backups', 0),
        ]);

        // Assign the primary allocation to the server
        $primaryAllocation->update(['server_id' => $server->id]);

        // Assign additional allocations if any
        if (!empty($allocations)) {
            foreach ($allocations as $allocationData) {
                $ip = Arr::get($allocationData, 'ip');
                $port = Arr::get($allocationData, 'port');
                $isPrimary = Arr::get($allocationData, 'is_primary', false);

                if ($isPrimary) {
                    continue; // Skip primary allocation, already assigned
                }

                $allocation = Allocation::where('node_id', $node->id)
                    ->where('ip', $ip)
                    ->where('port', $port)
                    ->whereNull('server_id')
                    ->first();

                if ($allocation) {
                    $allocation->update(['server_id' => $server->id]);
                }
            }
        }

        // Import variables
        if (isset($config['variables'])) {
            $this->importVariables($server, $config['variables']);
        }

        return $server;
    }

    protected function importVariables(Server $server, array $variables): void
    {
        foreach ($variables as $variable) {
            $envVariable = Arr::get($variable, 'env_variable');
            $value = Arr::get($variable, 'value');

            // Find the egg variable
            $eggVariable = $server->egg->variables()->where('env_variable', $envVariable)->first();

            if ($eggVariable) {
                // Create the server variable
                ServerVariable::create([
                    'server_id' => $server->id,
                    'variable_id' => $eggVariable->id,
                    'variable_value' => $value,
                ]);
            }
        }
    }

    protected function findNextAvailablePort(int $nodeId, string $ip, int $startPort): int
    {
        $port = $startPort + 1;
        $maxPort = 65535;

        while ($port <= $maxPort) {
            $exists = Allocation::where('node_id', $nodeId)
                ->where('ip', $ip)
                ->where('port', $port)
                ->exists();

            if (!$exists) {
                return $port;
            }

            $port++;
        }

        throw new InvalidFileUploadException("Could not find an available port for IP {$ip} starting from port {$startPort}");
    }
}
