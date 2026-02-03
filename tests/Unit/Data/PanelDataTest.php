<?php

namespace App\Tests\Unit\Data;

use App\Data\Application\ServerData;
use App\Models\Server;
use App\Models\User;
use App\Tests\TestCase;

class PanelDataTest extends TestCase
{
    /**
     * Test that ServerData can be created from a model.
     */
    public function test_server_data_from_model(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);

        $this->assertInstanceOf(ServerData::class, $data);
        $this->assertEquals($server->id, $data->id);
        $this->assertEquals($server->uuid, $data->uuid);
        $this->assertEquals($server->name, $data->name);
    }

    /**
     * Test that ServerData outputs standard format when isFractal is false.
     */
    public function test_server_data_standard_format(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);
        $data->setFractal(false);

        $array = $data->toArray();

        // Should not have 'object' and 'attributes' wrapping
        $this->assertArrayNotHasKey('object', $array);
        $this->assertArrayNotHasKey('attributes', $array);

        // Should have direct fields
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('uuid', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('limits', $array);
        $this->assertArrayHasKey('feature_limits', $array);
        $this->assertArrayHasKey('container', $array);
    }

    /**
     * Test that ServerData outputs Fractal-compatible format when isFractal is true.
     */
    public function test_server_data_fractal_format(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);
        $data->setFractal(true);

        $array = $data->toArray();

        // Should have Fractal structure
        $this->assertArrayHasKey('object', $array);
        $this->assertArrayHasKey('attributes', $array);
        $this->assertEquals('server', $array['object']);

        // Attributes should contain the actual data
        $this->assertArrayHasKey('id', $array['attributes']);
        $this->assertArrayHasKey('uuid', $array['attributes']);
        $this->assertArrayHasKey('name', $array['attributes']);
        $this->assertArrayHasKey('limits', $array['attributes']);
        $this->assertArrayHasKey('feature_limits', $array['attributes']);
        $this->assertArrayHasKey('container', $array['attributes']);
    }

    /**
     * Test that ServerData collection outputs Fractal-compatible format.
     */
    public function test_server_data_collection_fractal_format(): void
    {
        $user = User::factory()->create();
        $servers = Server::factory()->count(3)->create(['owner_id' => $user->id]);

        $dataItems = $servers->map(fn (Server $server) => ServerData::fromModel($server))->toArray();
        $collection = ServerData::collection($dataItems, true);

        // Should have list structure
        $this->assertArrayHasKey('object', $collection);
        $this->assertArrayHasKey('data', $collection);
        $this->assertEquals('list', $collection['object']);
        $this->assertCount(3, $collection['data']);

        // Each item should have Fractal structure
        foreach ($collection['data'] as $item) {
            $this->assertArrayHasKey('object', $item);
            $this->assertArrayHasKey('attributes', $item);
            $this->assertEquals('server', $item['object']);
        }
    }

    /**
     * Test that ServerData collection outputs standard format when isFractal is false.
     */
    public function test_server_data_collection_standard_format(): void
    {
        $user = User::factory()->create();
        $servers = Server::factory()->count(3)->create(['owner_id' => $user->id]);

        $dataItems = $servers->map(fn (Server $server) => ServerData::fromModel($server))->toArray();
        $collection = ServerData::collection($dataItems, false);

        // Should be a simple array
        $this->assertIsArray($collection);
        $this->assertCount(3, $collection);

        // Each item should NOT have Fractal wrapping
        foreach ($collection as $item) {
            $this->assertArrayNotHasKey('object', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('uuid', $item);
        }
    }

    /**
     * Test that the resource name is correct.
     */
    public function test_server_data_resource_name(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $user->id]);

        $data = ServerData::fromModel($server);

        $this->assertEquals('server', $data->getResourceName());
    }
}
