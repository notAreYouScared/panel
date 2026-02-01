<?php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PasskeysPlugin implements Plugin
{
    protected bool $enabled = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'passkeys';
    }

    public function register(Panel $panel): void
    {
        // Register passkeys functionality with the panel
        // This will add passkey management to the profile page
    }

    public function boot(Panel $panel): void
    {
        // Boot passkeys functionality
    }

    public function isEnabled(): bool
    {
        return $this->enabled && class_exists('Spatie\\LaravelPasskeys\\PasskeysServiceProvider');
    }

    public function enabled(bool $condition = true): static
    {
        $this->enabled = $condition;

        return $this;
    }
}
