<?php

namespace App\Tests\Integration\Api\Application;

use App\Data\Application\ServerData;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\EnvironmentService;
use App\Transformers\Api\Application\ServerTransformer;
use Illuminate\Support\Arr;

class ServerDataIntegrationTest extends ApplicationApiIntegrationTestCase
{
    /**
     * Test that ServerData in Fractal mode produces the same output as ServerTransformer.
     */
    public function test_server_data_fractal_mode_matches_transformer(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        // Get environment service to create proper transformer output
        $environmentService = app(EnvironmentService::class);
        $environment = $environmentService->handle($server);

        // Get transformer output
        $transformer = $this->getTransformer(ServerTransformer::class);
        $transformerOutput = $transformer->transform($server);

        // Get Data output
        $data = ServerData::fromModel($server, $environment);
        $data->setFractal(true);
        $dataOutput = $data->toArray();

        // Fractal wraps items, so we need to compare attributes
        $this->assertArrayHasKey('object', $dataOutput);
        $this->assertArrayHasKey('attributes', $dataOutput);
        $this->assertEquals('server', $dataOutput['object']);

        // Compare the attributes (excluding relationships which we'll test separately)
        $transformerData = Arr::except($transformerOutput, ['relationships']);
        $dataAttributes = Arr::except($dataOutput['attributes'], ['relationships']);

        // Sort both arrays for comparison
        $transformerData = Arr::sortRecursive($transformerData);
        $dataAttributes = Arr::sortRecursive($dataAttributes);

        $this->assertEquals(
            json_encode($transformerData),
            json_encode($dataAttributes),
            'ServerData attributes do not match ServerTransformer output'
        );
    }

    /**
     * Test that ServerData collection in Fractal mode produces the correct list structure.
     */
    public function test_server_data_collection_fractal_mode_structure(): void
    {
        $user = User::factory()->create();
        $servers = Server::factory()->count(3)->create(['owner_id' => $user->id]);

        $dataItems = $servers->map(fn (Server $server) => ServerData::fromModel($server))->toArray();
        $collection = ServerData::collection($dataItems, true);

        // Should have Fractal list structure
        $this->assertArrayHasKey('object', $collection);
        $this->assertArrayHasKey('data', $collection);
        $this->assertEquals('list', $collection['object']);
        $this->assertCount(3, $collection['data']);

        // Each item should have Fractal structure
        foreach ($collection['data'] as $item) {
            $this->assertArrayHasKey('object', $item);
            $this->assertArrayHasKey('attributes', $item);
            $this->assertEquals('server', $item['object']);
            $this->assertArrayHasKey('id', $item['attributes']);
            $this->assertArrayHasKey('uuid', $item['attributes']);
            $this->assertArrayHasKey('name', $item['attributes']);
        }
    }

    /**
     * Test that ServerData in non-Fractal mode produces clean output.
     */
    public function test_server_data_non_fractal_mode_clean_output(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);
        $data->setFractal(false);
        $output = $data->toArray();

        // Should not have Fractal wrapping
        $this->assertArrayNotHasKey('object', $output);
        $this->assertArrayNotHasKey('attributes', $output);

        // Should have direct fields
        $this->assertArrayHasKey('id', $output);
        $this->assertArrayHasKey('uuid', $output);
        $this->assertArrayHasKey('name', $output);
        $this->assertArrayHasKey('limits', $output);
        $this->assertArrayHasKey('feature_limits', $output);
        $this->assertArrayHasKey('container', $output);
    }

    /**
     * Test that nested objects are properly structured.
     */
    public function test_server_data_nested_objects(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);
        $output = $data->toArray();

        // Check limits structure
        $this->assertIsArray($output['limits']);
        $this->assertArrayHasKey('memory', $output['limits']);
        $this->assertArrayHasKey('swap', $output['limits']);
        $this->assertArrayHasKey('disk', $output['limits']);
        $this->assertArrayHasKey('cpu', $output['limits']);

        // Check feature_limits structure
        $this->assertIsArray($output['feature_limits']);
        $this->assertArrayHasKey('databases', $output['feature_limits']);
        $this->assertArrayHasKey('allocations', $output['feature_limits']);
        $this->assertArrayHasKey('backups', $output['feature_limits']);

        // Check container structure
        $this->assertIsArray($output['container']);
        $this->assertArrayHasKey('startup_command', $output['container']);
        $this->assertArrayHasKey('image', $output['container']);
        $this->assertArrayHasKey('installed', $output['container']);
        $this->assertArrayHasKey('environment', $output['container']);
    }
}
