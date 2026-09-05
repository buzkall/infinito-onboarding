<?php

namespace Arzcode\InfinitoOnboarding\Tests;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\AdminPanelProvider;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Arzcode\\InfinitoOnboarding\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            SupportServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            InfinitoOnboardingServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('view.compiled', sys_get_temp_dir() . '/infinito-onboarding-views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');

        foreach ([
            'create_onboarding_tours_table',
            'create_onboarding_tour_steps_table',
            'create_onboarding_tour_completions_table',
            'create_onboarding_tour_events_table',
            'add_translations_to_onboarding_tables',
            'add_meta_to_onboarding_tour_completions_table',
        ] as $migration) {
            $migration = include __DIR__ . "/../database/migrations/{$migration}.php.stub";
            $migration->up();
        }
    }
}
