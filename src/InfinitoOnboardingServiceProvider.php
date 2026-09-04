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
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        //
    }

    public function packageBooted(): void
    {
        //
    }
}
