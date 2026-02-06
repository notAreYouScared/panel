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
        // This plugin doesn't need to register additional resources or pages
        // The passkey functionality is integrated directly into the EditProfile page
        // which is already part of the Filament authentication system
    }

    public function boot(Panel $panel): void
    {
        // This plugin doesn't need boot-time initialization
        // The passkey routes are conditionally registered in routes/auth.php
        // and the UI is part of the EditProfile page schema
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
