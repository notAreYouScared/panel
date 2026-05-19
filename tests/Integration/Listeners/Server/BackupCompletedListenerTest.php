<?php

namespace App\Tests\Integration\Listeners\Server;

use App\Events\Server\BackupCompleted;
use App\Facades\Activity;
use App\Listeners\Server\BackupCompletedListener;
use App\Models\Backup;
use App\Tests\Integration\IntegrationTestCase;

class BackupCompletedListenerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.email.send_backup_completed_notification', false);
    }

    public function test_it_sends_a_database_notification_for_user_initiated_backups(): void
    {
        $server = $this->createServerModel();
        $backup = Backup::factory()->create(['server_id' => $server->id]);

        Activity::event('server:backup.start')->subject($backup)->log();

        app(BackupCompletedListener::class)->handle(new BackupCompleted($backup));

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_it_does_not_send_a_database_notification_for_scheduled_backups(): void
    {
        $server = $this->createServerModel();
        $backup = Backup::factory()->create(['server_id' => $server->id]);

        app(BackupCompletedListener::class)->handle(new BackupCompleted($backup));

        $this->assertDatabaseCount('notifications', 0);
    }
}
