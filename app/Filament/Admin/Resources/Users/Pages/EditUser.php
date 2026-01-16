<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Facades\AdminActivity;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserUpdateService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\IconSize;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = UserResource::class;

    private UserUpdateService $service;

    public function boot(UserUpdateService $service): void
    {
        $this->service = $service;
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(fn (User $user) => user()?->id === $user->id ? trans('admin/user.self_delete') : ($user->servers()->count() > 0 ? trans('admin/user.has_servers') : trans('filament-actions::delete.single.modal.actions.delete.label')))
                ->disabled(fn (User $user) => user()?->id === $user->id || $user->servers()->count() > 0)
                ->after(function (User $user) {
                    AdminActivity::event('user:deleted')
                        ->property('username', $user->username)
                        ->property('email', $user->email)
                        ->property('id', $user->id)
                        ->withRequestMetadata()
                        ->log();
                })
                ->iconButton()->iconSize(IconSize::ExtraLarge),
            $this->getSaveFormAction()->formId('form')
                ->iconButton()->iconSize(IconSize::ExtraLarge)
                ->icon('tabler-device-floppy'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (!$record instanceof User) {
            return $record;
        }
        unset($data['roles'], $data['avatar']);

        $originalEmail = $record->email;
        $hasPassword = isset($data['password']);

        $user = $this->service->handle($record, $data);

        // Log changes
        if ($hasPassword) {
            AdminActivity::event('user:password:changed')
                ->subject($user)
                ->property('username', $user->username)
                ->withRequestMetadata()
                ->log();
        }

        if ($originalEmail !== $user->email) {
            AdminActivity::event('user:email:changed')
                ->subject($user)
                ->properties([
                    'username' => $user->username,
                    'old' => $originalEmail,
                    'new' => $user->email,
                ])
                ->withRequestMetadata()
                ->log();
        } elseif (!$hasPassword && !empty($user->getChanges())) {
            // Only log general update if not password/email change
            AdminActivity::event('user:updated')
                ->subject($user)
                ->property('username', $user->username)
                ->properties($user->getChanges())
                ->withRequestMetadata()
                ->log();
        }

        return $user;
    }
}
