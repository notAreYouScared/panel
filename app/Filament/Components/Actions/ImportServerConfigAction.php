<?php

namespace App\Filament\Components\Actions;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Services\Servers\Sharing\ServerConfigCreatorService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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

        $this->modalDescription('Import server configuration from a YAML file to create a new server.');

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

        $this->action(function (ServerConfigCreatorService $createService, array $data): void {
            /** @var UploadedFile $file */
            $file = $data['file'];

            try {
                $server = $createService->fromFile($file);
                
                Notification::make()
                    ->title('Server Created')
                    ->body("Server '{$server->name}' has been successfully created from configuration.")
                    ->success()
                    ->send();

                // Redirect to the new server's edit page
                redirect()->route('filament.admin.resources.servers.edit', ['record' => $server]);
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
