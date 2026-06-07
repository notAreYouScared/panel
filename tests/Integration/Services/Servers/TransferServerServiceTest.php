<?php

namespace App\Tests\Integration\Services\Servers;

use App\Enums\NodeJwtTokenType;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Services\Servers\TransferServerService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;

class TransferServerServiceTest extends IntegrationTestCase
{
    public function test_transfer_notifications_use_server_transfer_token_type(): void
    {
        /** @var Server $server */
        $server = $this->createServerModel();
        /** @var Node $newNode */
        $newNode = Node::factory()->create();
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $newNode->id]);

        Http::fake([
            $server->node->getConnectionAddress() . '/*' => Http::response(),
        ]);

        $this->app->make(TransferServerService::class)->handle($server, $newNode->id, $allocation->id);

        Http::assertSent(function (Request $request) use ($newNode, $server) {
            $payload = $request->data();
            $token = substr($payload['token'], 7);

            $key = InMemory::plainText($newNode->daemon_token);
            $config = Configuration::forSymmetricSigner(new Sha256(), $key);
            $parsed = $config->parser()->parse($token);

            $this->assertInstanceOf(UnencryptedToken::class, $parsed);
            $this->assertSame(NodeJwtTokenType::ServerTransfer->value, $parsed->claims()->get('token_type'));
            $this->assertSame($server->uuid, $parsed->claims()->get('sub'));

            return $request->url() === $server->node->getConnectionAddress() . "/api/servers/{$server->uuid}/transfer";
        });
    }
}
