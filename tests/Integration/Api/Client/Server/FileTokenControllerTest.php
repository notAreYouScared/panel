<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\NodeJwtTokenType;
use App\Enums\SubuserPermission;
use App\Models\Backup;
use App\Models\Server;
use App\Models\User;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Illuminate\Http\Response;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;

class FileTokenControllerTest extends ClientApiIntegrationTestCase
{
    public function test_upload_url_uses_file_upload_token_type(): void
    {
        /** @var User $user */
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileCreate]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/files/upload");

        $response->assertOk();
        $response->assertJsonStructure(['attributes' => ['url']]);

        $claims = $this->parseNodeTokenClaims($server, $response->json('attributes.url'));

        $this->assertSame($server->uuid, $claims->get('server_uuid'));
        $this->assertSame($user->uuid, $claims->get('user_uuid'));
        $this->assertSame(NodeJwtTokenType::FileUpload->value, $claims->get('token_type'));
    }

    public function test_download_url_uses_file_download_token_type(): void
    {
        /** @var User $user */
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileReadContent]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/files/download?file=logs/latest.log");

        $response->assertOk();
        $response->assertJsonStructure(['attributes' => ['url']]);

        $claims = $this->parseNodeTokenClaims($server, $response->json('attributes.url'));

        $this->assertSame($server->uuid, $claims->get('server_uuid'));
        $this->assertSame($user->uuid, $claims->get('user_uuid'));
        $this->assertSame('logs/latest.log', $claims->get('file_path'));
        $this->assertSame(NodeJwtTokenType::FileDownload->value, $claims->get('token_type'));
    }

    public function test_backup_download_url_uses_backup_download_token_type(): void
    {
        /** @var User $user */
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::BackupDownload]);

        /** @var Backup $backup */
        $backup = Backup::factory()->for($server)->create();

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/backups/{$backup->uuid}/download");

        $response->assertOk();
        $response->assertJsonStructure(['attributes' => ['url']]);

        $claims = $this->parseNodeTokenClaims($server, $response->json('attributes.url'));

        $this->assertSame($server->uuid, $claims->get('server_uuid'));
        $this->assertSame($user->uuid, $claims->get('user_uuid'));
        $this->assertSame($backup->uuid, $claims->get('backup_uuid'));
        $this->assertSame(NodeJwtTokenType::BackupDownload->value, $claims->get('token_type'));
    }

    public function test_upload_requires_file_create_permission(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::WebsocketConnect]);

        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/files/upload")
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    private function parseNodeTokenClaims(Server $server, string $signedUrl): DataSet
    {
        $query = parse_url($signedUrl, PHP_URL_QUERY);
        parse_str($query ?? '', $parameters);

        $this->assertArrayHasKey('token', $parameters);

        $key = InMemory::plainText($server->node->daemon_token);
        $config = Configuration::forSymmetricSigner(new Sha256(), $key);

        $token = $config->parser()->parse($parameters['token']);
        $this->assertInstanceOf(UnencryptedToken::class, $token);

        return $token->claims();
    }
}
