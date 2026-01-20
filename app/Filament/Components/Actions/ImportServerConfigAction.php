<?php

namespace App\Filament\Components\Actions;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Server;
use App\Services\Servers\Sharing\ServerConfigImporterService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Width;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

        $this->authorize(fn () => user()?->can('update server'));

        $this->modalHeading(fn (Server $server) => 'Import Configuration: ' . $server->name);

        $this->modalDescription('Import server configuration from a YAML file. This will update the server\'s settings, limits, allocations, and variable values.');

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
        ]);

        $this->action(function (ServerConfigImporterService $service, Server $server, array $data): void {
            /** @var TemporaryUploadedFile $file */
            $file = $data['file'];

            try {
                $service->fromFile($file, $server);

                Notification::make()
                    ->title('Configuration Imported')
                    ->body('Server configuration has been successfully imported and applied.')
                    ->success()
                    ->send();

                // Refresh the page to show updated values
                redirect()->to(request()->url());
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
