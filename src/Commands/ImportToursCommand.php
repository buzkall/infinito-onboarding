<?php

namespace Arzcode\InfinitoOnboarding\Commands;

use Arzcode\InfinitoOnboarding\Support\TourExporter;
use Arzcode\InfinitoOnboarding\Support\TourImporter;
use Illuminate\Console\Command;

class ImportToursCommand extends Command
{
    protected $signature = 'onboarding:import
        {--path= : Directory containing the JSON files (defaults to config infinito-onboarding.export_path)}
        {--fresh : Delete tours that are not present in the directory}';

    protected $description = 'Import onboarding tours from JSON files, matching on key and never duplicating';

    public function handle(TourImporter $importer): int
    {
        $directory = $this->option('path') ?: TourExporter::defaultPath();

        if (! is_dir($directory)) {
            $this->components->error("Directory [{$directory}] does not exist.");

            return self::FAILURE;
        }

        $result = $importer->importDirectory($directory, fresh: (bool) $this->option('fresh'));

        foreach ($result['imported'] as $tour) {
            $this->components->twoColumnDetail($tour->key, "{$tour->name} (v{$tour->version}, {$tour->steps->count()} steps)");
        }

        foreach ($result['deleted'] as $key) {
            $this->components->twoColumnDetail($key, '<fg=red>deleted</>');
        }

        $this->components->info(sprintf('Imported %d tour(s)%s.', count($result['imported']), $result['deleted'] !== [] ? ', deleted ' . count($result['deleted']) : ''));

        return self::SUCCESS;
    }
}
