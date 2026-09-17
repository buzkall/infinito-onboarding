<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Migrations are published to the path resolved when the provider booted.
    $this->bootedMigrationsPath = database_path('migrations');

    // Inside the base path, so the prompts show the same relative paths as in a real app.
    $this->root = base_path('infinito-onboarding-install-' . uniqid());
    $this->app->useAppPath($this->root . '/app');
    $this->app->usePublicPath($this->root . '/public');
    $this->app->useDatabasePath($this->root . '/database');

    $this->provider = $this->root . '/app/Providers/Filament/AdminPanelProvider.php';
    File::ensureDirectoryExists(dirname($this->provider));
    File::ensureDirectoryExists($this->root . '/database/migrations');
    File::put($this->provider, <<<'PHP'
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;
        use Filament\PanelProvider;

        class AdminPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->id('admin')
                    ->plugins([
                        FooPlugin::make(),
                    ]);
            }
        }

        PHP);

    $this->originalProvider = File::get($this->provider);
    $this->registerQuestion = 'Register the plugin in ' . basename($this->root) . '/app/Providers/Filament/AdminPanelProvider.php?';
});

afterEach(function (): void {
    File::deleteDirectory($this->root);

    foreach (InfinitoOnboardingServiceProvider::migrationNames() as $name) {
        File::delete(glob("{$this->bootedMigrationsPath}/*_{$name}.php") ?: []);
    }
});

it('registers the plugin with the chosen options and publishes the assets', function (): void {
    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Onboarding tours resource to the panel?', 'yes')
        ->expectsConfirmation('Add the "What\'s new" button to the topbar?', 'no')
        ->assertSuccessful();

    expect(File::get($this->provider))
        ->toContain('use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;')
        ->toContain(<<<'PHP'
                        FooPlugin::make(),
                        InfinitoOnboardingPlugin::make()
                            ->resource()
                            ->authorize(fn (): bool => app()->isLocal()),
                    ]);
        PHP)
        ->not->toContain('topbarTrigger')
        ->and(public_path('js/arzcode/infinito-onboarding/infinito-onboarding.js'))->toBeFile();
});

it('leaves a panel that already registers the plugin untouched', function (): void {
    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Onboarding tours resource to the panel?', 'no')
        ->expectsConfirmation('Add the "What\'s new" button to the topbar?', 'no')
        ->assertSuccessful();

    $installed = File::get($this->provider);

    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->assertSuccessful();

    expect(File::get($this->provider))->toBe($installed)
        ->and(substr_count($installed, 'InfinitoOnboardingPlugin::make()'))->toBe(1);
});

it('uninstalls everything the user agrees to remove', function (): void {
    Process::fake();

    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Onboarding tours resource to the panel?', 'yes')
        ->expectsConfirmation('Add the "What\'s new" button to the topbar?', 'yes')
        ->assertSuccessful();

    Tour::factory()->create();
    File::put(database_path('migrations/2026_01_01_000000_create_onboarding_tours_table.php'), '<?php');
    DB::table('migrations')->insert(['migration' => '2026_01_01_000000_create_onboarding_tours_table', 'batch' => 1]);

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'yes')
        ->expectsConfirmation('Drop the onboarding tables?', 'yes')
        ->expectsConfirmation('Delete 1 published onboarding migration file(s)?', 'yes')
        ->expectsConfirmation('Run `composer remove arzcode/infinito-onboarding` now?', 'yes')
        ->assertSuccessful();

    expect(File::get($this->provider))->toBe($this->originalProvider)
        ->and(Schema::hasTable('onboarding_tours'))->toBeFalse()
        ->and(Schema::hasTable('onboarding_tour_events'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%onboarding%')->exists())->toBeFalse()
        ->and(glob(database_path('migrations/*onboarding*')))->toBe([])
        ->and(public_path('js/arzcode/infinito-onboarding'))->not->toBeDirectory();

    Process::assertRan('composer remove arzcode/infinito-onboarding');
});

it('keeps the data and files the user declines to remove', function (): void {
    Process::fake();
    Tour::factory()->create();

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'yes')
        ->expectsConfirmation('Drop the onboarding tables?', 'no')
        ->expectsConfirmation('Run `composer remove arzcode/infinito-onboarding` now?', 'no')
        ->assertSuccessful();

    expect(Tour::count())->toBe(1);
    Process::assertNothingRan();
});

it('does nothing when the uninstall is not confirmed', function (): void {
    Tour::factory()->create();

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'no')
        ->assertSuccessful();

    expect(Tour::count())->toBe(1);
});
