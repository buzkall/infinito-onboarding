<?php

namespace Arzcode\InfinitoOnboarding;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InfinitoOnboardingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'infinito-onboarding';

    public static string $viewNamespace = 'infinito-onboarding';

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
        //
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
