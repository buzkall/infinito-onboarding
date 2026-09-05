<?php

namespace Arzcode\InfinitoOnboarding\Support;

/**
 * Which locales tour content can be authored in.
 *
 * `config('infinito-onboarding.locales')` lists them (e.g. ['en', 'es']);
 * the first one is the default and is stored in the base columns. When the
 * list is empty only the app locale is used and no translation tabs appear.
 */
class Locales
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $locales = config('infinito-onboarding.locales', []);
        $locales = is_array($locales) ? array_values(array_filter(array_map('strval', $locales))) : [];

        return $locales === [] ? [static::appLocale()] : array_values(array_unique($locales));
    }

    public static function default(): string
    {
        return static::all()[0];
    }

    /**
     * Locales other than the default: the ones that live in the JSON column.
     *
     * @return array<int, string>
     */
    public static function extra(): array
    {
        return array_slice(static::all(), 1);
    }

    public static function fallback(): string
    {
        $fallback = config('infinito-onboarding.fallback_locale') ?: config('app.fallback_locale');

        return is_string($fallback) && $fallback !== '' ? $fallback : static::default();
    }

    public static function isMultilingual(): bool
    {
        return count(static::all()) > 1;
    }

    public static function label(string $locale): string
    {
        $labels = config('infinito-onboarding.locale_labels', []);

        return is_array($labels) && isset($labels[$locale]) ? (string) $labels[$locale] : strtoupper($locale);
    }

    protected static function appLocale(): string
    {
        $locale = config('app.locale');

        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }
}
