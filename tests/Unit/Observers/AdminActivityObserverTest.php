<?php

namespace App\Tests\Unit\Observers;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['root_admin' => true]);
    Auth::login($this->admin);

    // Mock Filament panel to simulate admin context
    $this->mockFilamentPanel();
});

test('observer logs user creation in admin context', function () {
    $newUser = User::factory()->create();

    $log = AdminActivityLog::where('event', 'admin:User:created')->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($this->admin->id);
    expect($log->subject_id)->toBe($newUser->id);
    expect($log->subject_type)->toBe($newUser->getMorphClass());
});

test('observer logs user update in admin context', function () {
    $user = User::factory()->create(['username' => 'oldname']);

    $user->update(['username' => 'newname']);

    $log = AdminActivityLog::where('event', 'admin:User:updated')->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($this->admin->id);
    expect($log->subject_id)->toBe($user->id);
    expect($log->properties['changes']['username']['old'])->toBe('oldname');
    expect($log->properties['changes']['username']['new'])->toBe('newname');
});

test('observer logs user deletion in admin context', function () {
    $user = User::factory()->create();
    $userId = $user->id;

    $user->delete();

    $log = AdminActivityLog::where('event', 'admin:User:deleted')->first();

    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($this->admin->id);
    expect($log->subject_id)->toBe($userId);
});

test('observer does not log activity logs themselves', function () {
    $initialCount = AdminActivityLog::count();

    AdminActivityLog::create([
        'event' => 'test:event',
        'ip' => '127.0.0.1',
        'properties' => [],
    ]);

    // Should only have the one we explicitly created, no additional log from observer
    expect(AdminActivityLog::count())->toBe($initialCount + 1);
});

test('observer hides password changes in logs', function () {
    $user = User::factory()->create();

    $user->update(['password' => bcrypt('newpassword')]);

    $log = AdminActivityLog::where('event', 'admin:User:updated')->first();

    expect($log)->not->toBeNull();
    expect($log->properties['changes']['password']['old'])->toBe('***');
    expect($log->properties['changes']['password']['new'])->toBe('***');
});

// Helper function to mock Filament panel context
function mockFilamentPanel()
{
    $panel = \Mockery::mock(\Filament\Panel::class);
    $panel->shouldReceive('getId')->andReturn('admin');

    \Filament\Facades\Filament::shouldReceive('getCurrentPanel')
        ->andReturn($panel);
}
