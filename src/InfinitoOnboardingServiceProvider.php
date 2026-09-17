<?php

namespace Arzcode\InfinitoOnboarding;

use Arzcode\InfinitoOnboarding\Commands\ExportToursCommand;
use Arzcode\InfinitoOnboarding\Commands\ForgetUserCommand;
use Arzcode\InfinitoOnboarding\Commands\ImportToursCommand;
use Arzcode\InfinitoOnboarding\Commands\PruneEventsCommand;
use Arzcode\InfinitoOnboarding\Commands\UninstallCommand;
use Arzcode\InfinitoOnboarding\Livewire\ChangelogTrigger;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Macros\TourTargetMacro;
use Arzcode\InfinitoOnboarding\Support\Installer;
use Arzcode\InfinitoOnboarding\Support\Segments;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function Laravel\Prompts\intro;

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
            ->hasMigrations(static::migrationNames())
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(fn (InstallCommand $command): InstallCommand => $command
                ->startWith(fn () => intro('Installing Infinito Onboarding'))
                ->publishMigrations()
                ->endWith(fn (InstallCommand $command) => (new Installer)->run($command))
                // Spatie hides install commands from `artisan list`.
                ->setHidden(false)
                ->setDescription('Publish and run the migrations, publish the assets and register the plugin in your panels'));
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Segments::class);
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
            ForgetUserCommand::class,
            ImportToursCommand::class,
            PruneEventsCommand::class,
            UninstallCommand::class,
        ];
    }

    /**
     * Also used by the uninstaller to find the published copies.
     *
     * @return array<string>
     */
    public static function migrationNames(): array
    {
        return [
            'create_onboarding_tours_table',
            'create_onboarding_tour_steps_table',
            'create_onboarding_tour_completions_table',
            'create_onboarding_tour_events_table',
            'add_translations_to_onboarding_tables',
            'add_meta_to_onboarding_tour_completions_table',
            'add_user_index_to_onboarding_tour_events_table',
        ];
    }
}
