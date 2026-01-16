<?php

namespace App\Facades;

use App\Services\Activity\AdminActivityLogService;
use Illuminate\Support\Facades\Facade;

class AdminActivity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdminActivityLogService::class;
    }
}
