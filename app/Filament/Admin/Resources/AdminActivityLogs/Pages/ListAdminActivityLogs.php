<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs\Pages;

use App\Filament\Admin\Resources\AdminActivityLogs\AdminActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminActivityLogs extends ListRecords
{
    protected static string $resource = AdminActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No actions - logs cannot be created, edited, or deleted
        ];
    }
}
