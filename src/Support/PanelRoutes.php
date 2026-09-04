<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Filament\Panel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/**
 * Lists the GET routes registered under a panel's path, as pattern
 * suggestions for the route_pattern field.
 */
class PanelRoutes
{
    /**
     * @return array<int, string>
     */
    public static function patternsFor(?Panel $panel): array
    {
        if ($panel === null) {
            return [];
        }

        $prefix = trim($panel->getPath(), '/');

        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true))
            ->map(fn (Route $route): string => trim($route->uri(), '/'))
            ->filter(fn (string $uri): bool => $prefix === '' || $uri === $prefix || Str::startsWith($uri, $prefix . '/'))
            ->reject(fn (string $uri): bool => Str::contains($uri, ['livewire', 'filament/']))
            ->map(fn (string $uri): string => preg_replace('/\{[^}]+\}/', '*', $uri) ?? $uri)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
