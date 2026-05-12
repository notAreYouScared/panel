<?php

namespace App\Traits;

use App\Observers\AdminActivityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([AdminActivityObserver::class])]
trait HasAdminActivityLogging {}
