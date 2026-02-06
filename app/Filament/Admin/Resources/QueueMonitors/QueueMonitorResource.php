<?php

namespace App\Filament\Admin\Resources\QueueMonitors;

use App\Filament\Admin\Resources\QueueMonitors\Pages\ListQueueMonitors;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Components\Tables\Columns\ProgressBarColumn;
use App\Models\QueueMonitor;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QueueMonitorResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;

    protected static ?string $model = QueueMonitor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-chart-dots';

    public static function getNavigationLabel(): string
    {
        return 'Queue Jobs';
    }

    public static function getModelLabel(): string
    {
        return 'Queue Job';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Queue Jobs';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNull('finished_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('job_id')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->maxLength(255),
                TextInput::make('queue')
                    ->maxLength(255),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                Toggle::make('failed')
                    ->required(),
                TextInput::make('attempt')
                    ->required()
                    ->numeric(),
                TextInput::make('progress')
                    ->numeric(),
                Textarea::make('exception_message')
                    ->maxLength(65535),
            ]);
    }

    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'primary',
                        'succeeded' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('name')
                    ->label('Job Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('queue')
                    ->label('Queue')
                    ->searchable()
                    ->sortable(),
                ProgressBarColumn::make('progress')
                    ->label('Progress')
                    ->maxValue(1)
                    ->dangerColor('oklch(0.723 0.219 149.579)')
                    ->helperLabel(fn ($state) => $state !== null ? "{$state}%" : '0%')
                    ->sortable(),
                DateTimeColumn::make('started_at')
                    ->label('Started')
                    ->sortable()
                    ->since(),
                TextColumn::make('attempt')
                    ->label('Attempts')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('tabler-info-circle')
                    ->modalHeading('Job Details')
                    ->modalContent(fn (QueueMonitor $record) => view('filament.admin.resources.queue-monitors.details', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'Running',
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'succeeded') {
                            return $query
                                ->whereNotNull('finished_at')
                                ->where('failed', 0);
                        } elseif ($data['value'] === 'failed') {
                            return $query
                                ->whereNotNull('finished_at')
                                ->where('failed', 1);
                        } elseif ($data['value'] === 'running') {
                            return $query
                                ->whereNull('finished_at');
                        }
                    }),
                SelectFilter::make('queue')
                    ->label('Queue')
                    ->options(function () {
                        return QueueMonitor::query()
                            ->distinct()
                            ->pluck('queue', 'queue')
                            ->filter()
                            ->toArray();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQueueMonitors::route('/'),
        ];
    }
}
