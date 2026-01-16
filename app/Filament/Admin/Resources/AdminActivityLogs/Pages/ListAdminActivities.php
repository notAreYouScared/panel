<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs\Pages;

use App\Filament\Admin\Resources\AdminActivityLogs\AdminActivityResource;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Resources\Pages\ListRecords;

class ListAdminActivities extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = AdminActivityResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return trans('admin/activity.title');
    }
}
