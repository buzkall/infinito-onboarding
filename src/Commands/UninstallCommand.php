<?php

namespace Arzcode\InfinitoOnboarding\Commands;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Arzcode\InfinitoOnboarding\Support\PanelProviderPatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class UninstallCommand extends Command
{
    protected $signature = 'infinito-onboarding:uninstall';

    protected $description = 'Unregister the plugin and optionally drop its tables and delete its published files';

    protected const PACKAGE = 'arzcode/infinito-onboarding';

    public function handle(): int
    {
        intro('Uninstalling Infinito Onboarding');

        if (! confirm(label: 'This removes Infinito Onboarding from your application. Continue?', default: false)) {
            note('Aborted.');

            return self::SUCCESS;
        }

        $steps = [
            $this->unregisterPlugin(...),
            $this->deletePublishedAssets(...),
            $this->dropTables(...),
            $this->deletePublishedMigrations(...),
            fn () => $this->deletePublished(config_path('infinito-onboarding.php'), 'config file'),
            fn () => $this->deletePublished(lang_path('vendor/infinito-onboarding'), 'translations'),
            fn () => $this->deletePublished(resource_path('views/vendor/infinito-onboarding'), 'views'),
            $this->removePackage(...),
        ];

        foreach ($steps as $step) {
            $this->newLine();
            $step();
        }

        return self::SUCCESS;
    }

    protected function unregisterPlugin(): void
    {
        $registered = array_filter(
            PanelProviderPatcher::files(),
            fn (string $file): bool => PanelProviderPatcher::mentions((string) file_get_contents($file)),
        );

        if ($registered === []) {
            note('InfinitoOnboardingPlugin is not registered in any app/Providers/Filament/*PanelProvider.php.');

            return;
        }

        foreach ($registered as $file) {
            $contents = (string) file_get_contents($file);
            $patched = PanelProviderPatcher::remove($contents);
            $relative = $this->relativePath($file);

            if ($patched === $contents || ! PanelProviderPatcher::parses($patched)) {
                warning(sprintf('Could not remove InfinitoOnboardingPlugin from %s safely. Remove it by hand.', $relative));

                continue;
            }

            file_put_contents($file, $patched);

            PanelProviderPatcher::mentions($patched)
                ? warning(sprintf('InfinitoOnboardingPlugin is still referenced in %s. Remove it by hand.', $relative))
                : info(sprintf('Removed InfinitoOnboardingPlugin from %s.', $relative));
        }
    }

    protected function dropTables(): void
    {
        // Children first: they reference the tours table.
        $tables = array_filter(
            [
                config()->string('infinito-onboarding.table_names.tour_events', 'onboarding_tour_events'),
                config()->string('infinito-onboarding.table_names.tour_completions', 'onboarding_tour_completions'),
                config()->string('infinito-onboarding.table_names.tour_steps', 'onboarding_tour_steps'),
                config()->string('infinito-onboarding.table_names.tours', 'onboarding_tours'),
            ],
            fn (string $table): bool => Schema::hasTable($table),
        );

        if ($tables === []) {
            note('No onboarding tables found.');

            return;
        }

        if (! confirm(label: 'Drop the onboarding tables?', default: false, hint: 'This permanently deletes every tour, step, completion and analytics event.')) {
            note('Skipped. The onboarding tables are left in place.');

            return;
        }

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
            note("Dropped {$table}.");
        }

        // The tables are gone, so their migrations must be able to run again.
        if (Schema::hasTable('migrations')) {
            $ours = DB::table('migrations')
                ->pluck('migration')
                ->filter(fn (mixed $migration): bool => is_string($migration) && $this->isPackageMigration($migration));

            DB::table('migrations')->whereIn('migration', $ours->all())->delete();
        }

        info('Onboarding tables dropped.');
    }

    protected function deletePublishedMigrations(): void
    {
        $files = array_values(array_filter(
            glob(database_path('migrations/*.php')) ?: [],
            fn (string $file): bool => $this->isPackageMigration(basename($file, '.php')),
        ));

        if ($files === []) {
            note('No published onboarding migrations found.');

            return;
        }

        if (! confirm(label: sprintf('Delete %d published onboarding migration file(s)?', count($files)), default: false)) {
            note('Skipped. The published migrations are left in place.');

            return;
        }

        File::delete($files);
        info('Published migrations deleted.');
    }

    protected function deletePublishedAssets(): void
    {
        $asset = InfinitoOnboardingServiceProvider::$assetPackage;
        $dirs = array_filter([public_path("js/{$asset}"), public_path("css/{$asset}")], is_dir(...));

        if ($dirs === []) {
            note('No published assets found.');

            return;
        }

        foreach ($dirs as $dir) {
            File::deleteDirectory($dir);

            // Drop the vendor folder only when no other package of the vendor publishes there.
            if (File::isEmptyDirectory(dirname($dir))) {
                File::deleteDirectory(dirname($dir));
            }
        }

        info('Published assets deleted.');
    }

    protected function deletePublished(string $path, string $what): void
    {
        if (! file_exists($path)) {
            return;
        }

        $relative = $this->relativePath($path);

        if (! confirm(label: "Delete the published {$what} ({$relative})?", default: false)) {
            note("Skipped. The published {$what} are left in place.");

            return;
        }

        is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
        info("Deleted {$relative}.");
    }

    protected function removePackage(): void
    {
        // Without the flag Composer may also upgrade the package's dependencies (Filament, …).
        $command = 'composer remove ' . self::PACKAGE . ' --no-update-with-dependencies';

        if (! confirm(label: 'Run `composer remove ' . self::PACKAGE . '` now?', default: true)) {
            outro("Done. Run `{$command}` to finish the uninstall.");

            return;
        }

        $result = Process::path(base_path())
            ->forever()
            ->run($command, fn (string $type, string $output) => $this->output->write($output));

        if (! $result->successful()) {
            error("`{$command}` failed. Run it by hand to finish the uninstall.");

            return;
        }

        outro('Infinito Onboarding uninstalled.');
    }

    /**
     * Matches `2026_01_01_000000_create_onboarding_tables` exactly, so
     * another package's migration sharing a suffix is never touched.
     */
    protected function isPackageMigration(string $migration): bool
    {
        return collect(InfinitoOnboardingServiceProvider::migrationNames())
            ->contains(fn (string $name): bool => preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($name, '/') . '$/', $migration) === 1);
    }

    protected function relativePath(string $path): string
    {
        return Str::after($path, base_path() . DIRECTORY_SEPARATOR);
    }
}
