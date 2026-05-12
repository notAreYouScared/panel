<?php

namespace App\Traits;

use App\Observers\AdminActivityObserver;

trait HasAdminActivityLogging
{
    public static function bootHasAdminActivityLogging(): void
    {
        static::observe(AdminActivityObserver::class);
    }
}
