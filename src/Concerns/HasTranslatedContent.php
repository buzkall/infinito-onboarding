<?php

namespace Arzcode\InfinitoOnboarding\Concerns;

use Arzcode\InfinitoOnboarding\Support\Locales;

/**
 * Multi-language content without a hard dependency on a translation package.
 *
 * The base columns (e.g. `title`, `body`) hold the default locale; other
 * locales live in the `translations` JSON column as
 * `{"es": {"title": "…", "body": "…"}}`. translated() resolves the current
 * locale → fallback locale → base column.
 *
 * @property array<string, array<string, string|null>>|null $translations
 */
trait HasTranslatedContent
{
    /**
     * @return array<int, string>
     */
    abstract public function translatableAttributes(): array;

    public function translated(string $attribute, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        foreach (array_unique([$locale, Locales::fallback()]) as $candidate) {
            if ($candidate === Locales::default()) {
                break;
            }

            $value = $this->translations[$candidate][$attribute] ?? null;

            if (is_string($value) && ! static::isBlankContent($value)) {
                return $value;
            }
        }

        $base = $this->getAttribute($attribute);

        return $base === null ? null : (string) $base;
    }

    /**
     * Every locale's value for an attribute, the default locale included.
     *
     * @return array<string, string|null>
     */
    public function getTranslations(string $attribute): array
    {
        $values = [Locales::default() => $this->getAttribute($attribute)];

        foreach ($this->translations ?? [] as $locale => $attributes) {
            if (array_key_exists($attribute, $attributes)) {
                $values[$locale] = $attributes[$attribute];
            }
        }

        return $values;
    }

    public function setTranslation(string $locale, string $attribute, ?string $value): static
    {
        if (! in_array($attribute, $this->translatableAttributes(), true)) {
            return $this;
        }

        if ($locale === Locales::default()) {
            $this->setAttribute($attribute, $value);

            return $this;
        }

        $translations = $this->translations ?? [];
        $translations[$locale] = [...($translations[$locale] ?? []), $attribute => $value];
        $this->translations = static::cleanTranslations($translations);

        return $this;
    }

    /**
     * Empty strings and empty rich-text markup (`<p></p>`) both count as blank.
     */
    public static function isBlankContent(string $value): bool
    {
        return trim(strip_tags(str_replace(['&nbsp;', "\u{00A0}"], ' ', $value))) === '';
    }

    /**
     * Drop empty strings, unknown attributes and empty locales.
     *
     * @param  array<string, mixed>|null  $translations
     * @return array<string, array<string, string>>|null
     */
    public static function cleanTranslations(?array $translations): ?array
    {
        /** @var static $instance */
        $instance = app(static::class);
        $allowed = $instance->translatableAttributes();
        $clean = [];

        foreach ($translations ?? [] as $locale => $attributes) {
            if (! is_array($attributes) || $locale === Locales::default()) {
                continue;
            }

            foreach ($attributes as $attribute => $value) {
                if (! in_array($attribute, $allowed, true) || ! is_string($value) || static::isBlankContent($value)) {
                    continue;
                }

                $clean[$locale][$attribute] = $value;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
