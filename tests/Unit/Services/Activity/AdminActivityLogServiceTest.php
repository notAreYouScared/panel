<?php

namespace App\Tests\Unit\Services\Activity;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\Activity\AdminActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('admin activity log service creates log entry', function () {
    $service = app(AdminActivityLogService::class);

    $log = $service
        ->event('admin:User:created')
        ->actor($this->user)
        ->description('Test activity log')
        ->property('test', 'value')
        ->log();

    expect($log)->toBeInstanceOf(AdminActivityLog::class);
    expect($log->event)->toBe('admin:User:created');
    expect($log->description)->toBe('Test activity log');
    expect($log->actor_id)->toBe($this->user->id);
    expect($log->properties->get('test'))->toBe('value');
});

test('admin activity log service can set subject', function () {
    $service = app(AdminActivityLogService::class);
    $subject = User::factory()->create();

    $log = $service
        ->event('admin:User:updated')
        ->actor($this->user)
        ->subject($subject)
        ->log();

    expect($log->subject_id)->toBe($subject->id);
    expect($log->subject_type)->toBe($subject->getMorphClass());
});

test('admin activity log service can set multiple properties', function () {
    $service = app(AdminActivityLogService::class);

    $log = $service
        ->event('admin:User:updated')
        ->actor($this->user)
        ->property([
            'field1' => 'value1',
            'field2' => 'value2',
        ])
        ->log();

    expect($log->properties->get('field1'))->toBe('value1');
    expect($log->properties->get('field2'))->toBe('value2');
});

test('admin activity log service handles anonymous actor', function () {
    $service = app(AdminActivityLogService::class);

    $log = $service
        ->event('admin:System:action')
        ->anonymous()
        ->log();

    expect($log->actor_id)->toBeNull();
    expect($log->actor_type)->toBeNull();
});

test('admin activity log service can be used in transaction', function () {
    $service = app(AdminActivityLogService::class);

    $result = $service
        ->event('admin:User:created')
        ->actor($this->user)
        ->transaction(function ($service) {
            $service->property('transactional', true);
            return 'success';
        });

    expect($result)->toBe('success');
    expect(AdminActivityLog::where('event', 'admin:User:created')->count())->toBe(1);

    $log = AdminActivityLog::where('event', 'admin:User:created')->first();
    expect($log->properties->get('transactional'))->toBeTrue();
});

test('admin activity log rollback on transaction failure', function () {
    $service = app(AdminActivityLogService::class);

    try {
        $service
            ->event('admin:User:created')
            ->actor($this->user)
            ->transaction(function () {
                throw new \Exception('Test exception');
            });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Test exception');
    }

    expect(AdminActivityLog::where('event', 'admin:User:created')->count())->toBe(0);
});
