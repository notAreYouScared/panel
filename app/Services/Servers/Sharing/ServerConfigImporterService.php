<?php

namespace App\Services\Servers\Sharing;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;

class ServerConfigImporterService
{
    public function fromFile(UploadedFile $file, Server $server): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidFileUploadException('The selected file was not uploaded successfully');
        }

        try {
            $parsed = Yaml::parse($file->getContent());
        } catch (\Exception $exception) {
            throw new InvalidFileUploadException('Could not parse YAML file: ' . $exception->getMessage());
        }

        $this->applyConfiguration($server, $parsed);
    }

    public function applyConfiguration(Server $server, array $config): void
    {
        // Validate egg UUID exists
        $eggUuid = Arr::get($config, 'egg.uuid');
        $eggName = Arr::get($config, 'egg.name'); // NEW REQUIREMENT: Also get egg name

        if (!$eggUuid) {
            throw new InvalidFileUploadException('Egg UUID is required in the configuration file');
        }

        $egg = Egg::where('uuid', $eggUuid)->first();

        if (!$egg) {
            throw new InvalidFileUploadException(
                "Egg with UUID '{$eggUuid}'" .
                ($eggName ? " (name: {$eggName})" : '') .
                ' does not exist in the system'
            );
        }

        // Update server configuration
        $server->update([
            'egg_id' => $egg->id,
            'startup' => Arr::get($config, 'settings.startup', $server->startup),
            'image' => Arr::get($config, 'settings.image', $server->image),
            'skip_scripts' => Arr::get($config, 'settings.skip_scripts', $server->skip_scripts),
            'memory' => Arr::get($config, 'limits.memory', $server->memory),
            'swap' => Arr::get($config, 'limits.swap', $server->swap),
            'disk' => Arr::get($config, 'limits.disk', $server->disk),
            'io' => Arr::get($config, 'limits.io', $server->io),
            'cpu' => Arr::get($config, 'limits.cpu', $server->cpu),
            'threads' => Arr::get($config, 'limits.threads', $server->threads),
            'oom_killer' => Arr::get($config, 'limits.oom_killer', $server->oom_killer),
            'database_limit' => Arr::get($config, 'feature_limits.databases', $server->database_limit),
            'allocation_limit' => Arr::get($config, 'feature_limits.allocations', $server->allocation_limit),
            'backup_limit' => Arr::get($config, 'feature_limits.backups', $server->backup_limit),
        ]);

        // Optional: Update description
        if (isset($config['description'])) {
            $server->update(['description' => $config['description']]);
        }

        // Optional: Import variables
        if (isset($config['variables'])) {
            $this->importVariables($server, $config['variables']);
        }

        // Optional: Import allocations
        if (isset($config['allocations'])) {
            $this->importAllocations($server, $config['allocations']);
        }
    }

    protected function importVariables(Server $server, array $variables): void
    {
        foreach ($variables as $variable) {
            $envVariable = Arr::get($variable, 'env_variable');
            $value = Arr::get($variable, 'value');

            // Find the egg variable
            $eggVariable = EggVariable::where('egg_id', $server->egg_id)
                ->where('env_variable', $envVariable)
                ->first();

            if ($eggVariable) {
                // Update or create the server variable
                ServerVariable::updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'variable_id' => $eggVariable->id,
                    ],
                    [
                        'variable_value' => $value,
                    ]
                );
            }
        }
    }

    protected function importAllocations(Server $server, array $allocations): void
    {
        $nodeId = $server->node_id;
        $primaryAllocationSet = false;

        foreach ($allocations as $allocationData) {
            $ip = Arr::get($allocationData, 'ip');
            $port = Arr::get($allocationData, 'port');
            $isPrimary = Arr::get($allocationData, 'is_primary', false);

            // Check if allocation exists and is available
            $allocation = Allocation::where('node_id', $nodeId)
                ->where('ip', $ip)
                ->where('port', $port)
                ->first();

            // If allocation doesn't exist, create it
            if (!$allocation) {
                $allocation = Allocation::create([
                    'node_id' => $nodeId,
                    'ip' => $ip,
                    'port' => $port,
                    'server_id' => $server->id,
                ]);
            }
            // If allocation exists but is in use by another server, find next available port
            elseif ($allocation->server_id && $allocation->server_id !== $server->id) {
                $newPort = $this->findNextAvailablePort($nodeId, $ip, $port);

                $allocation = Allocation::create([
                    'node_id' => $nodeId,
                    'ip' => $ip,
                    'port' => $newPort,
                    'server_id' => $server->id,
                ]);
            }
            // If allocation exists and is available, assign it to the server
            elseif (!$allocation->server_id) {
                $allocation->update(['server_id' => $server->id]);
            }

            // Set as primary allocation if specified and not already set
            if ($isPrimary && !$primaryAllocationSet) {
                $server->update(['allocation_id' => $allocation->id]);
                $primaryAllocationSet = true;
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
