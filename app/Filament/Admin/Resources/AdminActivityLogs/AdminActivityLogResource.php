<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs;

use App\Enums\CustomizationKey;
use App\Filament\Admin\Resources\AdminActivityLogs\Pages\ListAdminActivityLogs;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyTable;
use Exception;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class AdminActivityLogResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyTable;

    protected static ?string $model = AdminActivityLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-history';

    protected static ?string $recordTitleAttribute = 'event';

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return trans('admin/admin_activity.nav_title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();
        return $count > 0 ? number_format($count) : null;
    }

    public static function getModelLabel(): string
    {
        return trans('admin/admin_activity.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/admin_activity.model_label_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    /**
     * @throws Exception
     */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('event')
                    ->label(trans('admin/admin_activity.event'))
                    ->html()
                    ->description(fn ($state) => $state)
                    ->icon(fn (AdminActivityLog $activityLog) => $activityLog->getIcon())
                    ->formatStateUsing(fn (AdminActivityLog $activityLog) => $activityLog->getLabel())
                    ->searchable(),
                TextColumn::make('actor.username')
                    ->label(trans('admin/admin_activity.actor'))
                    ->state(function (AdminActivityLog $activityLog) {
                        if (!$activityLog->actor instanceof User) {
                            return $activityLog->actor_id === null ? trans('admin/admin_activity.system') : trans('admin/admin_activity.deleted_user');
                        }

                        return $activityLog->actor->username . " ({$activityLog->actor->email})";
                    })
                    ->tooltip(fn (AdminActivityLog $activityLog) => user()?->can('view adminActivityLog') ? $activityLog->ip : '')
                    ->searchable(['username', 'email'])
                    ->grow(false),
                TextColumn::make('subject_type')
                    ->label(trans('admin/admin_activity.subject'))
                    ->state(function (AdminActivityLog $activityLog) {
                        if (!$activityLog->subject) {
                            return trans('admin/admin_activity.no_subject');
                        }

                        $subjectType = class_basename($activityLog->subject_type);
                        $subjectName = '';

                        if ($activityLog->subject instanceof User) {
                            $subjectName = $activityLog->subject->username;
                        } elseif (method_exists($activityLog->subject, 'getAttribute')) {
                            $subjectName = $activityLog->subject->getAttribute('name') ?? $activityLog->subject->getAttribute('id');
                        }

                        return $subjectType . ($subjectName ? ": {$subjectName}" : '');
                    })
                    ->searchable()
                    ->grow(false),
                DateTimeColumn::make('timestamp')
                    ->label(trans('admin/admin_activity.timestamp'))
                    ->since()
                    ->sortable()
                    ->grow(false),
            ])
            ->defaultSort('timestamp', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('event')
                            ->label(trans('admin/admin_activity.event'))
                            ->state(fn (AdminActivityLog $activityLog) => new HtmlString($activityLog->getLabel())),
                        TextInput::make('actor')
                            ->label(trans('admin/admin_activity.actor'))
                            ->formatStateUsing(function (AdminActivityLog $activityLog) {
                                if (!$activityLog->actor instanceof User) {
                                    return $activityLog->actor_id === null ? trans('admin/admin_activity.system') : trans('admin/admin_activity.deleted_user');
                                }

                                $actor = $activityLog->actor->username . " ({$activityLog->actor->email})";

                                if (user()?->can('view adminActivityLog')) {
                                    $actor .= " - $activityLog->ip";
                                }

                                return $actor;
                            }),
                        TextInput::make('subject')
                            ->label(trans('admin/admin_activity.subject'))
                            ->formatStateUsing(function (AdminActivityLog $activityLog) {
                                if (!$activityLog->subject) {
                                    return trans('admin/admin_activity.no_subject');
                                }

                                $subjectType = class_basename($activityLog->subject_type);
                                $subjectName = '';

                                if ($activityLog->subject instanceof User) {
                                    $subjectName = $activityLog->subject->username;
                                } elseif (method_exists($activityLog->subject, 'getAttribute')) {
                                    $subjectName = $activityLog->subject->getAttribute('name') ?? $activityLog->subject->getAttribute('id');
                                }

                                return $subjectType . ($subjectName ? ": {$subjectName}" : '');
                            }),
                        DateTimePicker::make('timestamp')
                            ->label(trans('admin/admin_activity.timestamp')),
                        KeyValue::make('properties')
                            ->label(trans('admin/admin_activity.metadata'))
                            ->formatStateUsing(fn ($state) => $state ? Arr::dot($state) : []),
                    ]),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options(fn (Table $table) => $table->getQuery()->pluck('event', 'event')->unique()->sort())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('actor_id')
                    ->label(trans('admin/admin_activity.actor'))
                    ->relationship('actor', 'username')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('timestamp', 'desc');
    }

    public static function canViewAny(): bool
    {
        return user()?->can('view adminActivityLog');
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListAdminActivityLogs::route('/'),
        ];
    }
}
