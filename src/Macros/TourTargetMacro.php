<?php

namespace Arzcode\InfinitoOnboarding\Macros;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Navigation\NavigationItem;
use Filament\Support\Components\Component;
use Filament\Tables\Columns\Column;

/**
 * Registers the `->tourTarget('key')` macro on Filament components.
 *
 * The macro stamps `data-tour="key"` onto the element a tour step should
 * highlight, picking the attribute bag that ends up on the visible wrapper:
 *
 *  - form Field            → the field wrapper (label + input)
 *  - infolist Entry        → the entry wrapper (label + value)
 *  - table Column          → the header cell (unique per column)
 *  - Action, NavigationItem and every other component → extraAttributes()
 *
 * Filament's Macroable resolves macros through the class hierarchy, so a
 * single registration on the base Component covers Actions, Fields, Columns,
 * NavigationItems, schema layout components and infolist entries.
 */
class TourTargetMacro
{
    public const ATTRIBUTE = 'data-tour';

    public static function register(): void
    {
        foreach (static::targets() as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (! method_exists($class, 'macro') || ! method_exists($class, 'hasMacro')) {
                continue;
            }

            if ($class::hasMacro('tourTarget')) {
                continue;
            }

            $class::macro('tourTarget', function (string $key): mixed {
                // Filament binds the closure to the component instance at call time.
                // @phpstan-ignore-next-line
                return TourTargetMacro::apply($this, $key);
            });
        }
    }

    /**
     * Classes the macro is explicitly registered on. The base Component is
     * enough for inheritance-based lookup, but the concrete classes are also
     * listed so the macro survives if a future Filament release stops
     * resolving macros through parents.
     *
     * @return array<int, class-string>
     */
    public static function targets(): array
    {
        return [
            Component::class,
            Action::class,
            Field::class,
            Column::class,
            NavigationItem::class,
            Entry::class,
        ];
    }

    /**
     * @template T of object
     *
     * @param  T  $component
     * @return T
     */
    public static function apply(object $component, string $key): object
    {
        $attributes = static::attributes($key);

        if ($component instanceof Field) {
            $component->extraFieldWrapperAttributes($attributes, merge: true);

            return $component;
        }

        if ($component instanceof Entry) {
            $component->extraEntryWrapperAttributes($attributes, merge: true);

            return $component;
        }

        if ($component instanceof Column) {
            $component->extraHeaderAttributes($attributes, merge: true);

            return $component;
        }

        if (method_exists($component, 'extraAttributes')) {
            $component->extraAttributes($attributes, merge: true);

            return $component;
        }

        if (method_exists($component, 'extraInputAttributes')) {
            $component->extraInputAttributes($attributes, merge: true);
        }

        return $component;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $key): array
    {
        return [static::ATTRIBUTE => static::sanitizeKey($key)];
    }

    /**
     * Keys are rendered unescaped inside an attribute, so only allow a safe
     * character set.
     */
    public static function sanitizeKey(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_\-.:]/', '-', trim($key)) ?? '';
    }
}
