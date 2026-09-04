<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Models\Tour;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Syncs JSON tour files into the database, matching on key and never
 * duplicating. Completions are left untouched.
 */
class TourImporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function importArray(array $data): Tour
    {
        if (isset($data['format']) && (int) $data['format'] > TourExporter::FORMAT_VERSION) {
            throw new InvalidArgumentException("Unsupported tour format version [{$data['format']}].");
        }

        return TourDefinition::fromArray($data)->save();
    }

    public function importJson(string $json): Tour
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Tour JSON must decode to an object.');
        }

        return $this->importArray($data);
    }

    public function importFile(string $path): Tour
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Tour file [{$path}] does not exist.");
        }

        return $this->importJson(File::get($path));
    }

    /**
     * Import every *.json file in the directory. With $fresh, tours that are
     * not present in the directory are deleted afterwards.
     *
     * @return array{imported: array<int, Tour>, deleted: array<int, string>}
     */
    public function importDirectory(?string $directory = null, bool $fresh = false): array
    {
        $directory ??= TourExporter::defaultPath();

        $imported = [];

        if (File::isDirectory($directory)) {
            foreach (File::glob(rtrim($directory, '/\\') . '/*.json') as $path) {
                $imported[] = $this->importFile($path);
            }
        }

        $deleted = [];

        if ($fresh) {
            $keys = array_map(fn (Tour $tour): string => $tour->key, $imported);

            $stale = Tour::query()->whereNotIn('key', $keys)->get();

            foreach ($stale as $tour) {
                $deleted[] = $tour->key;
                $tour->delete();
            }
        }

        return ['imported' => $imported, 'deleted' => $deleted];
    }
}
