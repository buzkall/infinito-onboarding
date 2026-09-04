<?php

namespace Arzcode\InfinitoOnboarding\Commands;

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Support\TourExporter;
use Illuminate\Console\Command;

class ExportToursCommand extends Command
{
    protected $signature = 'onboarding:export
        {tour? : Key of a single tour to export (exports every tour when omitted)}
        {--path= : Directory to write the JSON files to (defaults to config infinito-onboarding.export_path)}';

    protected $description = 'Export onboarding tours to JSON files so they can be committed and promoted between environments';

    public function handle(TourExporter $exporter): int
    {
        $query = Tour::query()->with('steps')->ordered();

        if ($key = $this->argument('tour')) {
            $query->where('key', $key);
        }

        $tours = $query->get();

        if ($tours->isEmpty()) {
            $this->components->warn($key ? "No tour with key [{$key}] found." : 'No tours to export.');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: TourExporter::defaultPath();

        foreach ($exporter->export($tours, $directory) as $path) {
            $this->components->twoColumnDetail(basename($path), $path);
        }

        $this->components->info("Exported {$tours->count()} tour(s) to [{$directory}].");

        return self::SUCCESS;
    }
}
