<?php

namespace App\Services\Helpers;

use App\Enums\PluginStatus;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Exception;
use Filament\Panel;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use ZipArchive;

class PluginService
{
    public function __construct(private Application $app) {}

    public function loadPlugins(): void
    {
        // Don't load any plugins during tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        /** @var ClassLoader $classLoader */
        $classLoader = File::getRequire(base_path('vendor/autoload.php'));

        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            try {
                // Filter out plugins that are not compatible with the current panel version
                if (!$plugin->isCompatible()) {
                    $this->setStatus($plugin, PluginStatus::Incompatible, 'This Plugin is only compatible with Panel version ' . $plugin->panel_version . (!$plugin->isPanelVersionStrict() ? ' or newer' : '') . ' but you are using version ' . config('app.version') . '!');

                    continue;
                } else {
                    // Make sure to update the status if a plugin is no longer incompatible (e.g. because the user changed their panel version)
                    if ($plugin->status === PluginStatus::Incompatible) {
                        $this->disablePlugin($plugin);
                    }
                }

                // Always autoload src directory to make sure all class names can be resolved (e.g. in migrations)
                $namespace = $plugin->namespace . '\\';
                if (!array_key_exists($namespace, $classLoader->getPrefixesPsr4())) {
                    $classLoader->setPsr4($namespace, plugin_path($plugin->id, 'src/'));

                    $classLoader->addPsr4('Database\Factories\\', plugin_path($plugin->id, 'database/Factories/'));
                    $classLoader->addPsr4('Database\Seeders\\', plugin_path($plugin->id, 'database/Seeders/'));
                }

                // Load config
                $config = plugin_path($plugin->id, 'config', $plugin->id . '.php');
                if (file_exists($config)) {
                    config()->set($plugin->id, require $config);
                }

                // Filter out plugins that should not be loaded (e.g. because they are disabled or not installed yet)
                if (!$plugin->shouldLoad()) {
                    continue;
                }

                // Load translations
                $translations = plugin_path($plugin->id, 'lang');
                if (file_exists($translations)) {
                    $this->app->afterResolving('translator', function ($translator) use ($plugin, $translations) {
                        if ($plugin->isLanguage()) {
                            $translator->addPath($translations);
                        } else {
                            $translator->addNamespace($plugin->id, $translations);
                        }
                    });
                }

                // Register service providers
                foreach ($plugin->getProviders() as $provider) {
                    if (!class_exists($provider) || !is_subclass_of($provider, ServiceProvider::class)) {
                        continue;
                    }

                    $this->app->register($provider);
                }

                // Resolve artisan commands
                foreach ($plugin->getCommands() as $command) {
                    if (!class_exists($command) || !is_subclass_of($command, Command::class)) {
                        continue;
                    }

                    ConsoleApplication::starting(function ($artisan) use ($command) {
                        $artisan->resolve($command);
                    });
                }

                // Load migrations
                $migrations = plugin_path($plugin->id, 'database', 'migrations');
                if (file_exists($migrations)) {
                    $this->app->afterResolving('migrator', function ($migrator) use ($migrations) {
                        $migrator->path($migrations);
                    });
                }

                // Load views
                $views = plugin_path($plugin->id, 'resources', 'views');
                if (file_exists($views)) {
                    $this->app->afterResolving('view', function ($view) use ($plugin, $views) {
                        $view->addNamespace($plugin->id, $views);
                    });
                }
            } catch (Exception $exception) {
                $this->handlePluginException($plugin, $exception);
            }
        }
    }

    public function loadPanelPlugins(Panel $panel): void
    {
        // Don't load any plugins during tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            try {
                if (!$plugin->shouldLoad($panel->getId())) {
                    continue;
                }

                $pluginClass = $plugin->fullClass();

                if (!class_exists($pluginClass)) {
                    throw new Exception('Class "' . $pluginClass . '" not found');
                }

                $panel->plugin(new $pluginClass());

                if ($plugin->status === PluginStatus::Errored) {
                    $this->enablePlugin($plugin);
                }
            } catch (Exception $exception) {
                $this->handlePluginException($plugin, $exception);
            }
        }
    }

    /**
     * @param  null|array<string, string>  $newPackages
     * @param  null|array<string, string>  $oldPackages
     */
    public function manageComposerPackages(?array $newPackages = [], ?array $oldPackages = null): void
    {
        $newPackages ??= [];

        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if (!$plugin->composer_packages) {
                continue;
            }

            if (!$plugin->shouldLoad()) {
                continue;
            }

            try {
                $pluginPackages = json_decode($plugin->composer_packages, true, 512, JSON_THROW_ON_ERROR);

                $newPackages = array_merge($newPackages, $pluginPackages);
            } catch (Exception $exception) {
                report($exception);
            }
        }

        $oldPackages = collect($oldPackages)
            ->filter(fn ($version, $package) => !array_key_exists($package, $newPackages))
            ->keys()
            ->unique()
            ->toArray();

        if (count($oldPackages) > 0) {
            $result = Process::path(base_path())->timeout(600)->run(['composer', 'remove', ...$oldPackages]);
            if ($result->failed()) {
                throw new Exception('Could not remove old composer packages: ' . $result->errorOutput());
            }
        }

        $newPackages = collect($newPackages)
            ->map(fn ($version, $package) => "$package:$version")
            ->flatten()
            ->unique()
            ->toArray();

        if (count($newPackages) > 0) {
            $result = Process::path(base_path())->timeout(600)->run(['composer', 'require', ...$newPackages]);
            if ($result->failed()) {
                throw new Exception('Could not require new composer packages: ' . $result->errorOutput());
            }
        }
    }

    public function runPluginMigrations(Plugin $plugin): void
    {
        $migrations = plugin_path($plugin->id, 'database', 'migrations');
        if (file_exists($migrations)) {
            $success = Artisan::call('migrate', ['--realpath' => true, '--path' => $migrations, '--force' => true]) === 0;

            if (!$success) {
                throw new Exception("Could not run migrations for plugin '{$plugin->id}'");
            }
        }
    }

    public function rollbackPluginMigrations(Plugin $plugin): void
    {
        $migrations = plugin_path($plugin->id, 'database', 'migrations');
        if (file_exists($migrations)) {
            $success = Artisan::call('migrate:rollback', ['--realpath' => true, '--path' => $migrations, '--force' => true]) === 0;

            if (!$success) {
                throw new Exception("Could not rollback migrations for plugin '{$plugin->id}'");
            }
        }
    }

    public function runPluginSeeder(Plugin $plugin): void
    {
        $seeder = $plugin->getSeeder();
        if ($seeder) {
            $success = Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]) === 0;

            if (!$success) {
                throw new Exception("Could not run seeder for plugin '{$plugin->id}'");
            }
        }
    }

    public function buildAssets(): bool
    {
        try {
            $result = Process::path(base_path())->timeout(300)->run('yarn install');
            if ($result->failed()) {
                throw new Exception('Could not install dependencies: ' . $result->errorOutput());
            }

            $result = Process::path(base_path())->timeout(600)->run('yarn build');
            if ($result->failed()) {
                throw new Exception('Could not build assets: ' . $result->errorOutput());
            }

            return true;
        } catch (Exception $exception) {
            if ($this->isDevModeActive()) {
                throw ($exception);
            }

            report($exception);
        }

        return false;
    }

    public function installPlugin(Plugin $plugin, bool $enable = true): void
    {
        try {
            $this->manageComposerPackages(json_decode($plugin->composer_packages, true, 512));

            if ($enable) {
                $this->enablePlugin($plugin);
            } else {
                if ($plugin->status === PluginStatus::NotInstalled) {
                    $this->disablePlugin($plugin);
                }
            }

            $this->buildAssets();

            $this->runPluginMigrations($plugin);

            $this->runPluginSeeder($plugin);
        } catch (Exception $exception) {
            $this->handlePluginException($plugin, $exception);
        }
    }

    public function updatePlugin(Plugin $plugin): void
    {
        try {
            $downloadUrl = $plugin->getDownloadUrlForUpdate();
            if ($downloadUrl) {
                $this->downloadPluginFromUrl($downloadUrl, true);

                $this->installPlugin($plugin, false);

                cache()->forget("plugins.$plugin->id.update");
            }
        } catch (Exception $exception) {
            $this->handlePluginException($plugin, $exception);
        }
    }

    public function uninstallPlugin(Plugin $plugin, bool $deleteFiles = false): void
    {
        try {
            $pluginPackages = json_decode($plugin->composer_packages, true, 512);

            $this->rollbackPluginMigrations($plugin);

            if ($deleteFiles) {
                $this->deletePlugin($plugin);
            } else {
                $this->setStatus($plugin, PluginStatus::NotInstalled);
            }

            $this->buildAssets();

            $this->manageComposerPackages(oldPackages: $pluginPackages);
        } catch (Exception $exception) {
            $this->handlePluginException($plugin, $exception);
        }
    }

    public function downloadPluginFromFile(UploadedFile $file, bool $cleanDownload = false): void
    {
        // Validate file size to prevent zip bombs
        $maxSize = config('panel.plugin.max_import_size');
        if ($file->getSize() > $maxSize) {
            throw new Exception("Zip file too large. ($maxSize  MiB)");
        }

        $zip = new ZipArchive();

        if (!$zip->open($file->getPathname())) {
            throw new Exception('Could not open zip file.');
        }

        // Validate zip contents before extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (Str::contains($filename, '..') || Str::startsWith($filename, '/')) {
                $zip->close();
                throw new Exception('Zip file contains invalid path traversal sequences.');
            }
        }

        $pluginName = str($file->getClientOriginalName())->before('.zip')->toString();

        if ($cleanDownload) {
            File::deleteDirectory(plugin_path($pluginName));
        }

        $extractPath = $zip->locateName($pluginName . '/plugin.json') !== false ? base_path('plugins') : plugin_path($pluginName);

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new Exception('Could not extract zip file.');
        }

        $zip->close();
    }

    public function downloadPluginFromUrl(string $url, bool $cleanDownload = false): void
    {
        // Check if this is a GitHub tree URL (folder URL)
        if ($this->isGitHubTreeUrl($url)) {
            $this->downloadPluginFromGitHub($url, $cleanDownload);
            return;
        }

        $info = pathinfo($url);
        $tmpDir = TemporaryDirectory::make()->deleteWhenDestroyed();
        $tmpPath = $tmpDir->path($info['basename']);

        $content = Http::timeout(60)->connectTimeout(5)->throw()->get($url)->body();

        // Validate file size to prevent zip bombs
        $maxSize = config('panel.plugin.max_import_size');
        if (strlen($content) > $maxSize) {
            throw new InvalidFileUploadException("Zip file too large. ($maxSize  MiB)");
        }

        if (!file_put_contents($tmpPath, $content)) {
            throw new InvalidFileUploadException('Could not write temporary file.');
        }

        $this->downloadPluginFromFile(new UploadedFile($tmpPath, $info['basename'], 'application/zip'), $cleanDownload);
    }

    private function isGitHubTreeUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $url);
    }

    private function downloadPluginFromGitHub(string $url, bool $cleanDownload = false): void
    {
        // Parse GitHub URL
        if (!preg_match('#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $url, $matches)) {
            throw new InvalidFileUploadException('Invalid GitHub URL format.');
        }

        $owner = $matches[1];
        $repo = $matches[2];
        $branch = $matches[3];
        $path = $matches[4];

        // Get the plugin name from the last part of the path
        $pluginName = basename($path);

        $tmpDir = TemporaryDirectory::make()->deleteWhenDestroyed();
        $pluginDir = $tmpDir->path($pluginName);

        // Create plugin directory if it doesn't exist
        if (!File::exists($pluginDir) && !File::makeDirectory($pluginDir, 0755, true)) {
            throw new InvalidFileUploadException('Could not create temporary directory.');
        }

        // Track total downloaded size
        $totalSize = 0;
        $maxSize = config('panel.plugin.max_import_size');

        // Download folder contents recursively
        $this->downloadGitHubFolder($owner, $repo, $branch, $path, $pluginDir, $totalSize, $maxSize);

        // Create a zip file
        $zipPath = $tmpDir->path($pluginName . '.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidFileUploadException('Could not create zip file.');
        }

        // Add all files to the zip
        $files = File::allFiles($pluginDir);
        foreach ($files as $file) {
            $relativePath = $pluginName . '/' . $file->getRelativePathname();
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();

        // Install the plugin using the existing method
        $this->downloadPluginFromFile(new UploadedFile($zipPath, $pluginName . '.zip', 'application/zip'), $cleanDownload);
    }

    private function downloadGitHubFolder(string $owner, string $repo, string $branch, string $path, string $localPath, int &$totalSize, int $maxSize): void
    {
        // GitHub API endpoint to get folder contents
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}?ref={$branch}";

        $response = Http::timeout(30)->connectTimeout(5)
            ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
            ->throw()
            ->get($apiUrl);

        $contents = $response->json();

        if (!is_array($contents)) {
            throw new InvalidFileUploadException('Invalid response from GitHub API.');
        }

        foreach ($contents as $item) {
            // Validate item name to prevent path traversal attacks
            if (!isset($item['name']) || Str::contains($item['name'], '..') || Str::startsWith($item['name'], '/')) {
                throw new InvalidFileUploadException('GitHub response contains invalid path traversal sequences.');
            }

            $itemPath = $localPath . '/' . $item['name'];

            if ($item['type'] === 'file') {
                // Validate download URL to ensure it's from GitHub
                if (!isset($item['download_url']) || !Str::startsWith($item['download_url'], 'https://raw.githubusercontent.com/')) {
                    throw new InvalidFileUploadException('Invalid file download URL from GitHub API.');
                }

                // Download file content
                $fileContent = Http::timeout(30)->connectTimeout(5)->throw()->get($item['download_url'])->body();

                // Track cumulative size across all files
                $fileSize = strlen($fileContent);
                $totalSize += $fileSize;
                
                if ($totalSize > $maxSize) {
                    $maxSizeMiB = round($maxSize / (1024 * 1024), 2);
                    throw new InvalidFileUploadException("Total download size exceeds maximum allowed size of {$maxSizeMiB} MiB");
                }

                if (!file_put_contents($itemPath, $fileContent)) {
                    throw new InvalidFileUploadException("Could not write file: {$item['name']}");
                }
            } elseif ($item['type'] === 'dir') {
                // Validate that path is properly set for directories
                if (!isset($item['path'])) {
                    throw new InvalidFileUploadException('Invalid directory path in GitHub API response.');
                }

                // Create directory if it doesn't exist and recurse
                if (!File::exists($itemPath) && !File::makeDirectory($itemPath, 0755, true)) {
                    throw new InvalidFileUploadException("Could not create directory: {$item['name']}");
                }

                $this->downloadGitHubFolder($owner, $repo, $branch, $item['path'], $itemPath, $totalSize, $maxSize);
            }
        }
    }

    public function deletePlugin(Plugin $plugin): void
    {
        File::deleteDirectory(plugin_path($plugin->id));
    }

    public function enablePlugin(string|Plugin $plugin): void
    {
        $this->setStatus($plugin, PluginStatus::Enabled);
    }

    public function disablePlugin(string|Plugin $plugin): void
    {
        $this->setStatus($plugin, PluginStatus::Disabled);
    }

    /** @param array<string, mixed> $data */
    private function setMetaData(string|Plugin $plugin, array $data): void
    {
        $path = plugin_path($plugin instanceof Plugin ? $plugin->id : $plugin, 'plugin.json');

        if (File::exists($path)) {
            $pluginData = File::json($path, JSON_THROW_ON_ERROR);
            $metaData = array_merge($pluginData['meta'] ?? [], $data);
            $pluginData['meta'] = $metaData;

            File::put($path, json_encode($pluginData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $plugin = $plugin instanceof Plugin ? $plugin : Plugin::findOrFail($plugin);
            $plugin->update($metaData);
        }
    }

    private function setStatus(string|Plugin $plugin, PluginStatus $status, ?string $message = null): void
    {
        $this->setMetaData($plugin, [
            'status' => $status,
            'status_message' => $message,
        ]);
    }

    /** @param array<int, string> $order */
    public function updateLoadOrder(array $order): void
    {
        foreach ($order as $i => $plugin) {
            $this->setMetaData($plugin, [
                'load_order' => $i,
            ]);
        }
    }

    public function hasThemePluginEnabled(): bool
    {
        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if ($plugin->isTheme() && $plugin->status === PluginStatus::Enabled) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function getPluginLanguages(): array
    {
        $languages = [];

        $plugins = Plugin::query()->orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if ($plugin->status !== PluginStatus::Enabled || !$plugin->isLanguage()) {
                continue;
            }

            $languages = array_merge($languages, collect(File::directories(plugin_path($plugin->id, 'lang')))->map(fn ($path) => basename($path))->toArray());
        }

        return array_unique($languages);
    }

    public function isDevModeActive(): bool
    {
        return config('panel.plugin.dev_mode', false);
    }

    private function handlePluginException(string|Plugin $plugin, Exception $exception): void
    {
        if ($this->isDevModeActive()) {
            throw ($exception);
        }

        report($exception);

        $this->setStatus($plugin, PluginStatus::Errored, $exception->getMessage());
    }
}
