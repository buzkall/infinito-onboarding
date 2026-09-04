<?php

namespace Arzcode\InfinitoOnboarding;

use Arzcode\InfinitoOnboarding\Macros\TourTargetMacro;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InfinitoOnboardingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'infinito-onboarding';

    public static string $viewNamespace = 'infinito-onboarding';

    public static string $assetPackage = 'arzcode/infinito-onboarding';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasMigrations($this->getMigrations());
    }

    public function packageRegistered(): void
    {
        //
    }

    public function packageBooted(): void
    {
        TourTargetMacro::register();

        FilamentAsset::register($this->getAssets(), package: static::$assetPackage);
    }

    /**
     * Built with `npm run build`; the dist output is committed so consumers
     * only need `php artisan filament:assets`.
     *
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Js::make('infinito-onboarding', __DIR__ . '/../resources/dist/infinito-onboarding.js'),
            Css::make('infinito-onboarding', __DIR__ . '/../resources/dist/infinito-onboarding.css'),
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_onboarding_tours_table',
            'create_onboarding_tour_steps_table',
            'create_onboarding_tour_completions_table',
        ];
    }
}
