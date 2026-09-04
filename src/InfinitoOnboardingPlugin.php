<?php

namespace Arzcode\InfinitoOnboarding;

use Filament\Contracts\Plugin;
use Filament\Panel;

class InfinitoOnboardingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'infinito-onboarding';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
