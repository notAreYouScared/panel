<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs;

use App\Filament\Admin\Resources\AdminActivityLogs\Pages\ListAdminActivities;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Traits\Filament\CanCustomizePages;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class AdminActivityResource extends Resource
{
    use CanCustomizePages;

    protected static ?string $model = AdminActivityLog::class;

    protected static ?int $navigationSort = 9;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-shield-check';

    protected static bool $isScopedToTenant = false;

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
                    ->label(trans('admin/activity.event'))
                    ->html()
                    ->description(fn ($state) => $state)
                    ->icon(fn (AdminActivityLog $activityLog) => $activityLog->getIcon())
                    ->formatStateUsing(fn (AdminActivityLog $activityLog) => $activityLog->getLabel())
                    ->searchable(),
                TextColumn::make('actor')
                    ->label(trans('admin/activity.actor'))
                    ->state(function (AdminActivityLog $activityLog) {
                        if (!$activityLog->actor instanceof User) {
                            return trans('admin/activity.system');
                        }

                        return $activityLog->actor->username . ' (' . $activityLog->actor->email . ')';
                    })
                    ->tooltip(fn (AdminActivityLog $activityLog) => $activityLog->ip)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph('actor', [User::class], function (Builder $query) use ($search) {
                            $query->where('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->grow(false),
                TextColumn::make('subject')
                    ->label(trans('admin/activity.subject'))
                    ->state(function (AdminActivityLog $activityLog) {
                        if (!$activityLog->subject) {
                            return '—';
                        }

                        $subject = $activityLog->subject;
                        $type = class_basename($subject);

                        if (method_exists($subject, 'name')) {
                            return "{$type}: {$subject->name}";
                        }

                        if (isset($subject->id)) {
                            return "{$type} #{$subject->id}";
                        }

                        return $type;
                    })
                    ->grow(false),
                DateTimeColumn::make('timestamp')
                    ->label(trans('admin/activity.timestamp'))
                    ->since()
                    ->sortable()
                    ->grow(false),
            ])
            ->defaultSort('timestamp', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('event')
                            ->label(trans('admin/activity.event'))
                            ->state(fn (AdminActivityLog $activityLog) => new HtmlString($activityLog->getLabel())),
                        TextInput::make('actor')
                            ->label(trans('admin/activity.actor'))
                            ->formatStateUsing(function (AdminActivityLog $activityLog) {
                                if (!$activityLog->actor instanceof User) {
                                    return trans('admin/activity.system');
                                }

                                return $activityLog->actor->username . ' (' . $activityLog->actor->email . ') - ' . $activityLog->ip;
                            }),
                        TextInput::make('subject')
                            ->label(trans('admin/activity.subject'))
                            ->formatStateUsing(function (AdminActivityLog $activityLog) {
                                if (!$activityLog->subject) {
                                    return '—';
                                }

                                $subject = $activityLog->subject;
                                $type = class_basename($subject);

                                if (method_exists($subject, 'name')) {
                                    return "{$type}: {$subject->name}";
                                }

                                if (isset($subject->id)) {
                                    return "{$type} #{$subject->id}";
                                }

                                return $type;
                            }),
                        DateTimePicker::make('timestamp')
                            ->label(trans('admin/activity.timestamp')),
                        KeyValue::make('properties')
                            ->label(trans('admin/activity.properties'))
                            ->visible(fn (AdminActivityLog $activityLog) => $activityLog->properties && !$activityLog->properties->isEmpty())
                            ->formatStateUsing(fn ($state) => Arr::dot($state)),
                        KeyValue::make('metadata')
                            ->label(trans('admin/activity.metadata'))
                            ->visible(fn (AdminActivityLog $activityLog) => $activityLog->hasAdditionalMetadata())
                            ->formatStateUsing(fn ($state) => Arr::dot($state)),
                    ]),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(trans('admin/activity.event'))
                    ->options(fn (Table $table) => $table->getQuery()->pluck('event', 'event')->unique()->sort())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('actor')
                    ->label(trans('admin/activity.actor'))
                    ->relationship('actor', 'username')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return AdminActivityLog::query();
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListAdminActivities::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/activity.title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/activity.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/activity.model_label_plural');
    }

    public static function canCreate(): bool
    {
        return false; // Admin activity logs are created programmatically only
    }
}
