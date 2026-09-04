<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * JSON interchange format for tours: what `onboarding:export` writes,
 * `onboarding:import` reads and record mode can round-trip.
 */
class TourExporter
{
    public const FORMAT_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Tour $tour): array
    {
        $tour->loadMissing('steps');

        return [
            'format' => self::FORMAT_VERSION,
            'key' => $tour->key,
            'name' => $tour->name,
            'description' => $tour->description,
            'mode' => $tour->mode->value,
            'route_pattern' => $tour->route_pattern,
            'version' => $tour->version,
            'published_at' => $tour->published_at?->toIso8601String(),
            'starts_at' => $tour->starts_at?->toIso8601String(),
            'ends_at' => $tour->ends_at?->toIso8601String(),
            'audience' => $tour->audience,
            'tenant_id' => $tour->tenant_id,
            'sort' => $tour->sort,
            'is_active' => $tour->is_active,
            'steps' => $tour->steps->map(fn (TourStep $step): array => [
                'target_type' => $step->target_type->value,
                'target' => $step->target,
                'title' => $step->title,
                'body' => $step->body,
                'placement' => $step->placement->value,
                'extra' => $step->extra,
            ])->values()->all(),
        ];
    }

    public function toJson(Tour $tour): string
    {
        return json_encode($this->toArray($tour), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Write one JSON file per tour into the directory and return the paths.
     *
     * @param  Collection<int, Tour>  $tours
     * @return array<int, string>
     */
    public function export(Collection $tours, ?string $directory = null): array
    {
        $directory ??= static::defaultPath();

        File::ensureDirectoryExists($directory);

        $paths = [];

        foreach ($tours as $tour) {
            $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $tour->key . '.json';
            File::put($path, $this->toJson($tour));
            $paths[] = $path;
        }

        return $paths;
    }

    public static function defaultPath(): string
    {
        return (string) config('infinito-onboarding.export_path', database_path('tours'));
    }
}
