<?php

namespace Arzcode\InfinitoOnboarding\Filament\Support;

use Arzcode\InfinitoOnboarding\Support\Locales;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Builds a "Translations" tab group with one tab per extra locale.
 *
 * $fields maps attribute name → closure(string $statePath, string $locale)
 * returning the field to render for that attribute; state paths are
 * `translations.{locale}.{attribute}` so the JSON column round-trips.
 *
 * Returns an empty array when only one locale is configured, so the
 * resource form stays untouched in single-language apps.
 *
 * @param  array<string, Closure>  $fields
 * @return array<int, Component>
 */
class TranslationTabs
{
    /**
     * @param  array<string, Closure(string $statePath, string $locale): Component>  $fields
     * @return array<int, Component>
     */
    public static function make(array $fields): array
    {
        if (! Locales::isMultilingual()) {
            return [];
        }

        $tabs = [];

        foreach (Locales::extra() as $locale) {
            $tabs[] = Tab::make($locale)
                ->label(Locales::label($locale))
                ->schema(collect($fields)
                    ->map(fn (Closure $factory, string $attribute): Component => $factory("translations.{$locale}.{$attribute}", $locale))
                    ->values()
                    ->all());
        }

        return [
            Tabs::make('translations')
                ->label(__('infinito-onboarding::onboarding.translations.heading'))
                ->columnSpanFull()
                ->tabs($tabs),
        ];
    }
}
