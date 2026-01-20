<?php

namespace App\Tests\Integration\Services\Servers\Sharing;

use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\Sharing\ServerConfigExporterService;
use App\Services\Servers\Sharing\ServerConfigImporterService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ServerConfigImportExportTest extends IntegrationTestCase
{
    protected Server $server;
    protected Egg $egg;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test server
        $user = User::factory()->create();
        $node = Node::factory()->create();
        $this->egg = Egg::factory()->create();
        
        $allocation = Allocation::factory()->create([
            'node_id' => $node->id,
        ]);

        $this->server = Server::factory()->create([
            'owner_id' => $user->id,
            'node_id' => $node->id,
            'egg_id' => $this->egg->id,
            'allocation_id' => $allocation->id,
            'name' => 'Test Server',
            'description' => 'Test Description',
            'memory' => 1024,
            'swap' => 512,
            'disk' => 10000,
            'cpu' => 100,
        ]);
    }

    public function test_server_config_can_be_exported(): void
    {
        $service = $this->app->make(ServerConfigExporterService::class);
        
        $yaml = $service->handle($this->server, [
            'include_description' => true,
            'include_allocations' => true,
            'include_variable_values' => true,
        ]);

        $this->assertNotEmpty($yaml);
        $this->assertStringContainsString('version: 1.0', $yaml);
        $this->assertStringContainsString('name: "Test Server"', $yaml);
        $this->assertStringContainsString('description: "Test Description"', $yaml);
        $this->assertStringContainsString('uuid: ' . $this->egg->uuid, $yaml);
        $this->assertStringContainsString('name: ' . $this->egg->name, $yaml); // NEW REQUIREMENT
        $this->assertStringContainsString('memory: 1024', $yaml);
        $this->assertStringContainsString('swap: 512', $yaml);
        $this->assertStringContainsString('disk: 10000', $yaml);
        $this->assertStringContainsString('cpu: 100', $yaml);
    }

    public function test_server_config_export_without_optional_fields(): void
    {
        $service = $this->app->make(ServerConfigExporterService::class);
        
        $yaml = $service->handle($this->server, [
            'include_description' => false,
            'include_allocations' => false,
            'include_variable_values' => false,
        ]);

        $this->assertNotEmpty($yaml);
        $this->assertStringContainsString('version: 1.0', $yaml);
        $this->assertStringNotContainsString('description:', $yaml);
        $this->assertStringNotContainsString('allocations:', $yaml);
        $this->assertStringNotContainsString('variables:', $yaml);
    }

    public function test_server_config_can_be_imported(): void
    {
        // Export first
        $exportService = $this->app->make(ServerConfigExporterService::class);
        $yaml = $exportService->handle($this->server);

        // Create a temporary file
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('config.yaml', $yaml);

        // Create a new server to import to
        $newServer = Server::factory()->create([
            'owner_id' => $this->server->owner_id,
            'node_id' => $this->server->node_id,
            'egg_id' => $this->egg->id,
            'allocation_id' => $this->server->allocation_id,
            'name' => 'New Server',
            'memory' => 512, // Different values
            'disk' => 5000,
        ]);

        // Import
        $importService = $this->app->make(ServerConfigImporterService::class);
        $importService->fromFile($file, $newServer);

        // Refresh the server
        $newServer->refresh();

        // Verify the import
        $this->assertEquals($this->egg->id, $newServer->egg_id);
        $this->assertEquals($this->server->memory, $newServer->memory);
        $this->assertEquals($this->server->disk, $newServer->disk);
        $this->assertEquals($this->server->cpu, $newServer->cpu);
    }

    public function test_import_fails_with_non_existent_egg_uuid(): void
    {
        $this->expectException(\App\Exceptions\Service\InvalidFileUploadException::class);
        $this->expectExceptionMessage('does not exist in the system');

        $yaml = <<<YAML
version: 1.0
name: Test
egg:
  uuid: 00000000-0000-0000-0000-000000000000
  name: NonExistent
settings:
  startup: java
  image: test
  skip_scripts: false
limits:
  memory: 1024
  swap: 512
  disk: 10000
  io: 500
  cpu: 100
  threads: null
  oom_killer: false
feature_limits:
  databases: 0
  allocations: 0
  backups: 0
YAML;

        $file = UploadedFile::fake()->createWithContent('config.yaml', $yaml);
        
        $importService = $this->app->make(ServerConfigImporterService::class);
        $importService->fromFile($file, $this->server);
    }
}
