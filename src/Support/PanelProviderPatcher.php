<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;

/**
 * Adds and removes the plugin registration in a host app's panel provider
 * source. Works on the file contents only, so it never loads the provider.
 */
class PanelProviderPatcher
{
    public const PLUGIN_CALL = 'InfinitoOnboardingPlugin::make()';

    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return glob(app_path('Providers/Filament/*PanelProvider.php')) ?: [];
    }

    public static function contains(string $contents): bool
    {
        return str_contains($contents, self::PLUGIN_CALL);
    }

    /**
     * Registers the plugin inside an existing `->plugins([...])` array, or adds
     * a `->plugin(...)` call to the end of the `return $panel` chain. Returns
     * null when the provider has neither shape.
     *
     * @param  list<string>  $chain  Plugin method calls, e.g. `->resource()`
     */
    public static function add(string $contents, array $chain = []): ?string
    {
        $patched = self::injectIntoPluginsArray($contents, $chain) ?? self::appendPluginCall($contents, $chain);

        return $patched === null ? null : self::addUseImport($patched);
    }

    /**
     * Removes every plugin registration (with its whole method chain) and the
     * `use` import.
     */
    public static function remove(string $contents): string
    {
        while (($start = strpos($contents, self::PLUGIN_CALL)) !== false) {
            $end = self::expressionEnd($contents, $start);

            if ($end === null) {
                break;
            }

            $contents = self::removeExpression($contents, $start, $end);
        }

        $import = '/^use\s+' . preg_quote(InfinitoOnboardingPlugin::class, '/') . ';[ \t]*\r?\n/m';

        return preg_replace($import, '', $contents) ?? $contents;
    }

    /**
     * @param  list<string>  $chain
     */
    protected static function injectIntoPluginsArray(string $contents, array $chain): ?string
    {
        if (! preg_match('/->plugins\(\s*\[/', $contents, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $open = $match[0][1] + strlen($match[0][0]) - 1;
        $close = self::matchingClose($contents, $open);

        if ($close === null) {
            return null;
        }

        $closeIndent = self::lineIndent($contents, $close);
        $before = rtrim(substr($contents, 0, $close));

        if (! str_ends_with($before, ',') && ! str_ends_with($before, '[')) {
            $before .= ',';
        }

        return $before . "\n" . self::entry($closeIndent . '    ', $chain) . "\n" . $closeIndent . substr($contents, $close);
    }

    /**
     * @param  list<string>  $chain
     */
    protected static function appendPluginCall(string $contents, array $chain): ?string
    {
        if (! preg_match('/return\s+\$panel\b/', $contents, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $semicolon = self::expressionEnd($contents, $match[0][1] + strlen($match[0][0]));

        if ($semicolon === null || $contents[$semicolon] !== ';') {
            return null;
        }

        $before = rtrim(substr($contents, 0, $semicolon));
        $lastLine = substr($before, (int) strrpos("\n" . $before, "\n"));
        $indent = (string) preg_replace('/\S.*$/', '', $lastLine);

        if (! str_starts_with(ltrim($lastLine), '->')) {
            $indent .= '    ';
        }

        return $before
            . "\n{$indent}->plugin(\n"
            . self::entry($indent . '    ', $chain)
            . "\n{$indent})"
            . substr($contents, $semicolon);
    }

    /**
     * @param  list<string>  $chain
     */
    protected static function entry(string $indent, array $chain): string
    {
        $lines = [$indent . self::PLUGIN_CALL];

        foreach ($chain as $call) {
            $lines[] = $indent . '    ' . $call;
        }

        return implode("\n", $lines) . ',';
    }

    protected static function addUseImport(string $contents): string
    {
        $import = 'use ' . InfinitoOnboardingPlugin::class . ';';

        if (str_contains($contents, $import)) {
            return $contents;
        }

        if (preg_match_all('/^use\s+[^;]+;\n/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $at = $last[1] + strlen($last[0]);

            return substr($contents, 0, $at) . $import . "\n" . substr($contents, $at);
        }

        if (preg_match('/^namespace\s+[^;]+;\n/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $at = $match[0][1] + strlen($match[0][0]);

            return substr($contents, 0, $at) . "\n" . $import . "\n" . substr($contents, $at);
        }

        return $contents;
    }

    /**
     * Cuts the expression at [$start, $end) together with the `->plugin(...)`
     * call wrapping it, or with its separator when it is an array item.
     */
    protected static function removeExpression(string $contents, int $start, int $end): string
    {
        $before = rtrim(substr($contents, 0, $start));

        if (preg_match('/->plugin\($/', $before)) {
            $callStart = strlen($before) - strlen('->plugin(');
            $callEnd = self::matchingClose($contents, strlen($before) - 1);

            if ($callEnd === null) {
                return $contents;
            }

            return rtrim(substr($contents, 0, $callStart)) . substr($contents, $callEnd + 1);
        }

        $lineStart = strrpos(substr($contents, 0, $start), "\n");
        $ownLine = $lineStart !== false && trim(substr($contents, $lineStart + 1, $start - $lineStart - 1)) === '';

        if ($contents[$end] === ',') {
            $after = $end + 1;

            if ($ownLine && preg_match('/\G[ \t]*(\/\/[^\n]*)?\r?\n/', $contents, $match, 0, $after)) {
                return substr($contents, 0, $lineStart + 1) . substr($contents, $after + strlen($match[0]));
            }

            preg_match('/\G[ \t]*/', $contents, $match, 0, $after);

            return substr($contents, 0, $ownLine ? $lineStart + 1 : $start) . substr($contents, $after + strlen($match[0]));
        }

        // Last item without a trailing comma: keep the closing bracket's line.
        $closeLineStart = strrpos(substr($contents, 0, $end), "\n");

        if ($ownLine && $closeLineStart !== false && $closeLineStart > $start && trim(substr($contents, $closeLineStart, $end - $closeLineStart)) === '') {
            return substr($contents, 0, $lineStart + 1) . substr($contents, $closeLineStart + 1);
        }

        return rtrim(rtrim(substr($contents, 0, $start)), ',') . substr($contents, $end);
    }

    /**
     * Position of the `,`, `;` or unmatched closing bracket that ends the
     * expression starting at $start.
     */
    protected static function expressionEnd(string $contents, int $start): ?int
    {
        $depth = 0;
        $length = strlen($contents);

        for ($i = $start; $i < $length; $i++) {
            $skipped = self::skipLiteral($contents, $i);

            if ($skipped !== $i) {
                $i = $skipped - 1;

                continue;
            }

            $char = $contents[$i];

            if (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    return $i;
                }

                $depth--;
            } elseif ($depth === 0 && ($char === ',' || $char === ';')) {
                return $i;
            }
        }

        return null;
    }

    protected static function matchingClose(string $contents, int $open): ?int
    {
        $end = self::expressionEnd($contents, $open + 1);

        while ($end !== null && ($contents[$end] === ',' || $contents[$end] === ';')) {
            $end = self::expressionEnd($contents, $end + 1);
        }

        return $end;
    }

    /**
     * Returns the offset right after a string or comment starting at $i, or $i
     * itself when there is none.
     */
    protected static function skipLiteral(string $contents, int $i): int
    {
        $char = $contents[$i];
        $next = $contents[$i + 1] ?? '';

        if ($char === '\'' || $char === '"') {
            $length = strlen($contents);

            for ($j = $i + 1; $j < $length; $j++) {
                if ($contents[$j] === '\\') {
                    $j++;
                } elseif ($contents[$j] === $char) {
                    return $j + 1;
                }
            }

            return $length;
        }

        if (($char === '/' && $next === '/') || ($char === '#' && $next !== '[')) {
            $newline = strpos($contents, "\n", $i);

            return $newline === false ? strlen($contents) : $newline;
        }

        if ($char === '/' && $next === '*') {
            $close = strpos($contents, '*/', $i + 2);

            return $close === false ? strlen($contents) : $close + 2;
        }

        return $i;
    }

    protected static function lineIndent(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, $offset), "\n");
        $from = $lineStart === false ? 0 : $lineStart + 1;
        $line = substr($contents, $from, $offset - $from);

        return (string) preg_replace('/\S.*$/s', '', $line);
    }
}
