<?php

namespace Arzcode\InfinitoOnboarding\Support;

/**
 * Narrows loosely typed values (config, JSON, form state, raw query rows)
 * to scalars, returning null for anything that cannot be represented.
 *
 * @internal
 */
final class Cast
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
