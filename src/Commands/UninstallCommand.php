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

    public function handle(): int
    {
        intro('Uninstalling Infinito Onboarding');

        if (! confirm(label: 'This removes Infinito Onboarding from your application. Continue?', default: false)) {
            note('Aborted.');

            return self::SUCCESS;
        }

        $steps = [
            $this->unregisterPlugin(...),
            $this->dropTables(...),
            $this->deletePublishedMigrations(...),
            $this->deletePublishedAssets(...),
            $this->deletePublishedOverrides(...),
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
            fn (string $file): bool => PanelProviderPatcher::contains((string) file_get_contents($file)),
        );

        if ($registered === []) {
            note('InfinitoOnboardingPlugin is not registered in any app/Providers/Filament/*PanelProvider.php.');

            return;
        }

        foreach ($registered as $file) {
            $patched = PanelProviderPatcher::remove((string) file_get_contents($file));
            file_put_contents($file, $patched);

            PanelProviderPatcher::contains($patched)
                ? warning(sprintf('Could not fully remove InfinitoOnboardingPlugin from %s. Remove it by hand.', $this->relativePath($file)))
                : info(sprintf('Removed InfinitoOnboardingPlugin from %s.', $this->relativePath($file)));
        }
    }

    protected function dropTables(): void
    {
        // Children first: they reference the tours table.
        $tables = array_filter(
            [
                config('infinito-onboarding.table_names.tour_events', 'onboarding_tour_events'),
                config('infinito-onboarding.table_names.tour_completions', 'onboarding_tour_completions'),
                config('infinito-onboarding.table_names.tour_steps', 'onboarding_tour_steps'),
                config('infinito-onboarding.table_names.tours', 'onboarding_tours'),
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
            DB::table('migrations')
                ->where(fn ($query) => collect(InfinitoOnboardingServiceProvider::migrationNames())
                    ->each(fn (string $name) => $query->orWhere('migration', 'like', "%_{$name}")))
                ->delete();
        }

        info('Onboarding tables dropped.');
    }

    protected function deletePublishedMigrations(): void
    {
        $files = collect(InfinitoOnboardingServiceProvider::migrationNames())
            ->flatMap(fn (string $name): array => glob(database_path("migrations/*_{$name}.php")) ?: [])
            ->all();

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
        }

        info('Published assets deleted.');
    }

    protected function deletePublishedOverrides(): void
    {
        $paths = array_filter(
            [
                config_path('infinito-onboarding.php'),
                lang_path('vendor/infinito-onboarding'),
                resource_path('views/vendor/infinito-onboarding'),
            ],
            file_exists(...),
        );

        if ($paths === []) {
            return;
        }

        $list = implode(', ', array_map($this->relativePath(...), $paths));

        if (! confirm(label: "Delete the published config, translations and views ({$list})?", default: false)) {
            note('Skipped. The published files are left in place.');

            return;
        }

        foreach ($paths as $path) {
            is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        info('Published config, translations and views deleted.');
    }

    protected function removePackage(): void
    {
        if (! confirm(label: 'Run `composer remove arzcode/infinito-onboarding` now?', default: true)) {
            outro('Done. Run `composer remove arzcode/infinito-onboarding` to finish the uninstall.');

            return;
        }

        $result = Process::path(base_path())
            ->forever()
            ->run('composer remove arzcode/infinito-onboarding', fn (string $type, string $output) => $this->output->write($output));

        if (! $result->successful()) {
            error('`composer remove arzcode/infinito-onboarding` failed. Run it by hand to finish the uninstall.');

            return;
        }

        outro('Infinito Onboarding uninstalled.');
    }

    protected function relativePath(string $path): string
    {
        return Str::after($path, base_path() . DIRECTORY_SEPARATOR);
    }
}
