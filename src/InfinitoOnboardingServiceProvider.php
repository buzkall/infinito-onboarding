<?php

namespace Arzcode\InfinitoOnboarding;

use Arzcode\InfinitoOnboarding\Commands\ExportToursCommand;
use Arzcode\InfinitoOnboarding\Commands\ImportToursCommand;
use Arzcode\InfinitoOnboarding\Livewire\ChangelogTrigger;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Macros\TourTargetMacro;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Component;
use Livewire\Livewire;
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
            ->hasMigrations($this->getMigrations())
            ->hasCommands($this->getCommands());
    }

    public function packageRegistered(): void
    {
        //
    }

    public function packageBooted(): void
    {
        TourTargetMacro::register();

        foreach ($this->getLivewireComponents() as $alias => $class) {
            Livewire::component($alias, $class);
        }

        FilamentAsset::register($this->getAssets(), package: static::$assetPackage);
    }

    /**
     * @return array<string, class-string<Component>>
     */
    protected function getLivewireComponents(): array
    {
        return [
            'infinito-onboarding.tour-overlay' => TourOverlay::class,
            'infinito-onboarding.tour-recorder' => TourRecorder::class,
            'infinito-onboarding.changelog-trigger' => ChangelogTrigger::class,
        ];
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
            // Only injected by the recorder view, so normal pages never load it.
            Js::make('infinito-onboarding-recorder', __DIR__ . '/../resources/dist/recorder.js')->loadedOnRequest(),
            Css::make('infinito-onboarding', __DIR__ . '/../resources/dist/infinito-onboarding.css'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            ExportToursCommand::class,
            ImportToursCommand::class,
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
