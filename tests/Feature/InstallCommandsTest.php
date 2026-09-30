<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Migrations are published to the path resolved when the provider booted.
    $this->bootedMigrationsPath = database_path('migrations');

    // Inside the base path, so the prompts show the same relative paths as in a real app.
    $this->root = base_path('infinito-onboarding-install-' . uniqid());
    $this->app->useAppPath($this->root . '/app');
    $this->app->usePublicPath($this->root . '/public');
    $this->app->useDatabasePath($this->root . '/database');
    $this->app->useConfigPath($this->root . '/config');
    $this->app->useLangPath($this->root . '/lang');

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
    $this->composerRemove = 'composer remove arzcode/infinito-onboarding --no-update-with-dependencies';
    $this->publishedMigrations = fn (): array => collect(InfinitoOnboardingServiceProvider::migrationNames())
        ->mapWithKeys(fn (string $name): array => [$name => count(glob("{$this->bootedMigrationsPath}/*_{$name}.php") ?: [])])
        ->all();
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
    File::deleteDirectory(resource_path('views/vendor/infinito-onboarding'));

    foreach (InfinitoOnboardingServiceProvider::migrationNames() as $name) {
        File::delete(glob("{$this->bootedMigrationsPath}/*_{$name}.php") ?: []);
    }
});

it('registers the plugin with the chosen options and publishes the assets', function (): void {
    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Tours resource to the panel?', 'yes')
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
        ->and(public_path('js/arzcode/infinito-onboarding/infinito-onboarding.js'))->toBeFile()
        ->and(($this->publishedMigrations)())->each->toBe(1);
});

it('leaves a panel that already registers the plugin untouched', function (): void {
    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Tours resource to the panel?', 'no')
        ->expectsConfirmation('Add the "What\'s new" button to the topbar?', 'no')
        ->assertSuccessful();

    $installed = File::get($this->provider);

    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->assertSuccessful();

    expect(File::get($this->provider))->toBe($installed)
        ->and(substr_count($installed, 'InfinitoOnboardingPlugin::make()'))->toBe(1)
        ->and(($this->publishedMigrations)())->each->toBe(1);
});

it('uninstalls everything the user agrees to remove', function (): void {
    Process::fake();

    $this->artisan('infinito-onboarding:install')
        ->expectsConfirmation('Publish the config file?', 'no')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation($this->registerQuestion, 'yes')
        ->expectsConfirmation('Add the Tours resource to the panel?', 'yes')
        ->expectsConfirmation('Add the "What\'s new" button to the topbar?', 'yes')
        ->assertSuccessful();

    Tour::factory()->create();
    File::put(database_path('migrations/2026_01_01_000000_create_onboarding_tables.php'), '<?php');
    DB::table('migrations')->insert(['migration' => '2026_01_01_000000_create_onboarding_tables', 'batch' => 1]);

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
        ->and(public_path('js/arzcode'))->not->toBeDirectory()
        ->and(public_path('css/arzcode'))->not->toBeDirectory();

    Process::assertRan($this->composerRemove);
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

it('leaves other packages alone', function (): void {
    Process::fake();

    File::put($this->provider, str_replace('FooPlugin::make(),', "FooPlugin::make(),\n                InfinitoOnboardingPlugin::make()->resource(),\n                BarPlugin::make(),", $this->originalProvider));
    File::put($this->provider, str_replace('use Filament\\Panel;', "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\nuse Filament\\Panel;", File::get($this->provider)));

    $otherAssets = [public_path('js/arzcode/other-package/other.js'), public_path('css/filament/filament/app.css')];
    $otherMigrations = ['2026_01_01_000000_create_users_table', '2026_01_01_000001_backup_create_onboarding_tables'];
    $otherTranslations = lang_path('vendor/other-package/en/messages.php');

    foreach ([...$otherAssets, $otherTranslations] as $file) {
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '');
    }

    foreach ($otherMigrations as $migration) {
        File::put(database_path("migrations/{$migration}.php"), '<?php');
        DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
    }

    File::ensureDirectoryExists(public_path('js/arzcode/infinito-onboarding'));
    File::put(database_path('migrations/2026_01_01_000002_create_onboarding_tables.php'), '<?php');
    DB::table('migrations')->insert(['migration' => '2026_01_01_000002_create_onboarding_tables', 'batch' => 1]);

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'yes')
        ->expectsConfirmation('Drop the onboarding tables?', 'yes')
        ->expectsConfirmation('Delete 1 published onboarding migration file(s)?', 'yes')
        ->expectsConfirmation('Run `composer remove arzcode/infinito-onboarding` now?', 'yes')
        ->assertSuccessful();

    expect(File::get($this->provider))
        ->not->toContain('InfinitoOnboarding')
        ->toContain("                FooPlugin::make(),\n                BarPlugin::make(),\n")
        ->and(public_path('js/arzcode/infinito-onboarding'))->not->toBeDirectory()
        ->and($otherTranslations)->toBeFile()
        ->and(DB::table('migrations')->whereIn('migration', $otherMigrations)->count())->toBe(2)
        ->and(DB::table('migrations')->where('migration', '2026_01_01_000002_create_onboarding_tables')->exists())->toBeFalse();

    foreach ($otherAssets as $file) {
        expect($file)->toBeFile();
    }

    foreach ($otherMigrations as $migration) {
        expect(database_path("migrations/{$migration}.php"))->toBeFile();
    }

    Process::assertRanTimes(fn ($process): bool => $process->command === $this->composerRemove, 1);
    Process::assertRanTimes(fn (): bool => true, 1);
});

it('asks separately about the published config, translations and views', function (): void {
    Process::fake();

    $config = config_path('infinito-onboarding.php');
    $translations = lang_path('vendor/infinito-onboarding/en/tours.php');
    $views = resource_path('views/vendor/infinito-onboarding/overlay.blade.php');

    foreach ([$config, $translations, $views] as $file) {
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '');
    }

    $relative = fn (string $path): string => Str::after($path, base_path() . DIRECTORY_SEPARATOR);

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'yes')
        ->expectsConfirmation('Drop the onboarding tables?', 'no')
        ->expectsConfirmation("Delete the published config file ({$relative($config)})?", 'yes')
        ->expectsConfirmation("Delete the published translations ({$relative(lang_path('vendor/infinito-onboarding'))})?", 'no')
        ->expectsConfirmation("Delete the published views ({$relative(resource_path('views/vendor/infinito-onboarding'))})?", 'yes')
        ->expectsConfirmation('Run `composer remove arzcode/infinito-onboarding` now?', 'no')
        ->assertSuccessful();

    expect($config)->not->toBeFile()
        ->and($translations)->toBeFile()
        ->and(resource_path('views/vendor/infinito-onboarding'))->not->toBeDirectory();
});

it('does not write a provider it cannot unpatch safely', function (): void {
    Process::fake();

    $contents = str_replace('FooPlugin::make(),', 'CustomInfinitoOnboardingPlugin::make(),', $this->originalProvider);
    File::put($this->provider, $contents);

    $this->artisan('infinito-onboarding:uninstall')
        ->expectsConfirmation('This removes Infinito Onboarding from your application. Continue?', 'yes')
        ->expectsOutputToContain('Remove it by hand')
        ->expectsConfirmation('Drop the onboarding tables?', 'no')
        ->expectsConfirmation('Run `composer remove arzcode/infinito-onboarding` now?', 'no')
        ->assertSuccessful();

    expect(File::get($this->provider))->toBe($contents);
});
