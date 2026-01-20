<?php

namespace App\Filament\Components\Actions;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Server;
use App\Services\Servers\Sharing\ServerConfigImporterService;
use App\Services\Servers\Sharing\ServerConfigCreatorService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;

class ImportServerConfigAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import_config';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Import Config');

        $this->icon('tabler-file-import');

        $this->iconSize(IconSize::ExtraLarge);

        $this->tooltip('Import server configuration from YAML file');

        $this->modalWidth(Width::Large);

        $this->authorize(fn () => user()?->can('create server'));

        $this->modalHeading('Import Server Configuration');

        $this->modalDescription('Import server configuration from a YAML file to create a new server or update an existing one.');

        $this->schema([
            FileUpload::make('file')
                ->label('Configuration File')
                ->hint('Upload a YAML file exported from another panel')
                ->acceptedFileTypes(['application/x-yaml', 'text/yaml', 'text/x-yaml', '.yaml', '.yml'])
                ->preserveFilenames()
                ->previewable(false)
                ->storeFiles(false)
                ->required()
                ->maxSize(1024), // 1MB max
            Select::make('mode')
                ->label('Import Mode')
                ->options([
                    'create' => 'Create New Server',
                    'update' => 'Update Existing Server',
                ])
                ->default('create')
                ->required()
                ->live(),
            Select::make('server_id')
                ->label('Target Server')
                ->options(fn () => Server::whereIn('node_id', user()?->accessibleNodes()->pluck('id'))->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->visible(fn (callable $get) => $get('mode') === 'update'),
        ]);

        $this->action(function (ServerConfigImporterService $importService, ServerConfigCreatorService $createService, array $data): void {
            /** @var UploadedFile $file */
            $file = $data['file'];
            $mode = $data['mode'];

            try {
                if ($mode === 'create') {
                    $server = $createService->fromFile($file);
                    
                    Notification::make()
                        ->title('Server Created')
                        ->body("Server '{$server->name}' has been successfully created from configuration.")
                        ->success()
                        ->send();

                    // Redirect to the new server's edit page
                    redirect()->route('filament.admin.resources.servers.edit', ['record' => $server]);
                } else {
                    $server = Server::findOrFail($data['server_id']);
                    $importService->fromFile($file, $server);

                    Notification::make()
                        ->title('Configuration Imported')
                        ->body("Server '{$server->name}' has been successfully updated.")
                        ->success()
                        ->send();

                    // Refresh the page
                    redirect()->to(request()->url());
                }
            } catch (InvalidFileUploadException $exception) {
                Notification::make()
                    ->title('Import Failed')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            } catch (\Exception $exception) {
                Notification::make()
                    ->title('Import Failed')
                    ->body('An unexpected error occurred: ' . $exception->getMessage())
                    ->danger()
                    ->send();

                report($exception);
            }
        });
    }
}
