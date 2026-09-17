<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

/**
 * The interactive steps `infinito-onboarding:install` runs after publishing
 * the migrations.
 */
class Installer
{
    public const AUTHORIZE_PLACEHOLDER = '->authorize(fn (): bool => app()->isLocal())';

    /** @var list<string> */
    protected array $patched = [];

    public function run(Command $command): void
    {
        $steps = [
            fn () => $this->publishConfig($command),
            fn () => $this->runMigrations($command),
            fn () => $this->publishAssets($command),
            $this->registerPlugin(...),
            $this->printNextSteps(...),
        ];

        foreach ($steps as $step) {
            $command->newLine();
            $step();
        }
    }

    protected function publishConfig(Command $command): void
    {
        if (file_exists(config_path('infinito-onboarding.php'))) {
            note('config/infinito-onboarding.php is already published.');

            return;
        }

        if (! confirm(label: 'Publish the config file?', default: false, hint: 'Only needed to rename tables or change query flags, locales or analytics.')) {
            return;
        }

        $command->callSilently('vendor:publish', ['--tag' => 'infinito-onboarding-config']);
        info('Published config/infinito-onboarding.php.');
    }

    protected function runMigrations(Command $command): void
    {
        if (! confirm(label: 'Run the migrations now?', default: true)) {
            note('Skipped. Run `php artisan migrate` when you are ready.');

            return;
        }

        $command->call('migrate');
    }

    protected function publishAssets(Command $command): void
    {
        info('Publishing Filament assets…');
        $command->call('filament:assets');
    }

    protected function registerPlugin(): void
    {
        $files = PanelProviderPatcher::files();

        if ($files === []) {
            warning('No app/Providers/Filament/*PanelProvider.php found. Register InfinitoOnboardingPlugin::make() in your panel provider by hand.');

            return;
        }

        $pending = [];

        foreach ($files as $file) {
            if (PanelProviderPatcher::contains((string) file_get_contents($file))) {
                note(sprintf('InfinitoOnboardingPlugin is already registered in %s.', $this->relativePath($file)));

                continue;
            }

            if (confirm(label: sprintf('Register the plugin in %s?', $this->relativePath($file)), default: true)) {
                $pending[] = $file;
            }
        }

        if ($pending === []) {
            return;
        }

        $chain = array_values(array_filter([
            confirm(label: 'Add the Onboarding tours resource to the panel?', default: true) ? '->resource()' : null,
            confirm(label: 'Add the "What\'s new" button to the topbar?', default: false) ? '->topbarTrigger()' : null,
            self::AUTHORIZE_PLACEHOLDER,
        ]));

        foreach ($pending as $file) {
            $patched = PanelProviderPatcher::add((string) file_get_contents($file), $chain);

            if ($patched === null) {
                warning(sprintf('Could not patch %s. Add ->plugin(InfinitoOnboardingPlugin::make()) by hand.', $this->relativePath($file)));

                continue;
            }

            file_put_contents($file, $patched);
            $this->patched[] = $file;
            info(sprintf('Registered InfinitoOnboardingPlugin in %s.', $this->relativePath($file)));
        }
    }

    protected function printNextSteps(): void
    {
        if ($this->patched !== []) {
            warning('Only local environments may manage, preview and record tours for now: replace the ->authorize() closure with your own check, e.g. fn (User $user): bool => $user->isAdmin().');
        }

        note('Record your first tour by opening any panel page with ?onboarding-record=my-first-tour.');
    }

    protected function relativePath(string $path): string
    {
        return Str::after($path, base_path() . DIRECTORY_SEPARATOR);
    }
}
