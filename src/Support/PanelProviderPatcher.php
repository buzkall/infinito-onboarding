<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Closure;
use ParseError;
use PhpToken;

/**
 * Adds and removes the plugin registration in a host app's panel provider
 * source. Works on PHP tokens, so strings and comments never throw off the
 * bracket matching, and never loads the provider.
 */
class PanelProviderPatcher
{
    public const PLUGIN_CALL = 'InfinitoOnboardingPlugin::make()';

    protected const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG];

    protected const OPENERS = ['(', '[', '{', '#[', '${'];

    protected const CLOSERS = [')', ']', '}'];

    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return glob(app_path('Providers/Filament/*PanelProvider.php')) ?: [];
    }

    /**
     * Whether the provider calls `make()` on this package's plugin class,
     * resolving imports and aliases, so a subclass or a namesake is ignored.
     */
    public static function contains(string $contents): bool
    {
        return self::registrations(self::tokens($contents)) !== [];
    }

    /**
     * Whether the plugin's name appears anywhere, comments and subclasses
     * included. Used to leave hand-edited providers alone.
     */
    public static function mentions(string $contents): bool
    {
        return str_contains($contents, class_basename(InfinitoOnboardingPlugin::class));
    }

    /**
     * Whether the patched file is still valid PHP, checked before writing it.
     */
    public static function parses(string $contents): bool
    {
        try {
            PhpToken::tokenize($contents, TOKEN_PARSE);

            return true;
        } catch (ParseError) {
            return false;
        }
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
        $tokens = self::tokens($contents);
        $patched = self::injectIntoPluginsArray($contents, $tokens, $chain) ?? self::appendPluginCall($contents, $tokens, $chain);

        return $patched === null ? null : self::addUseImport($patched);
    }

    /**
     * Removes every registration of the plugin (with its whole method chain)
     * and its `use` import, leaving every other plugin untouched. A
     * `->plugin(...)` call or `->plugins([])` array left empty goes too.
     */
    public static function remove(string $contents): string
    {
        while (($found = self::registrations($tokens = self::tokens($contents))) !== []) {
            $patched = self::removeRegistration($contents, $tokens, $found[0]);

            if ($patched === null) {
                break;
            }

            $contents = $patched;
        }

        $import = '/^use\s+\\\\?' . preg_quote(InfinitoOnboardingPlugin::class, '/') . '(\s+as\s+\w+)?\s*;[ \t]*\r?\n/mi';

        return preg_replace($import, '', $contents) ?? $contents;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @param  list<string>  $chain
     */
    protected static function injectIntoPluginsArray(string $contents, array $tokens, array $chain): ?string
    {
        [$from, $to] = self::returnStatement($tokens) ?? [0, count($tokens) - 1];

        for ($i = $from; $i + 3 <= $to; $i++) {
            if (! self::isMethodCall($tokens, $i + 1, 'plugins') || $tokens[$i + 3]->text !== '[') {
                continue;
            }

            $closeIndex = self::closingIndex($tokens, $i + 4, [']']);

            if ($closeIndex === null) {
                return null;
            }

            $close = $tokens[$closeIndex]->pos;
            $last = $tokens[$closeIndex - 1];

            // The comma goes right after the last entry, never after a comment following it.
            if (! in_array($last->text, [',', '['], true)) {
                $after = $last->pos + strlen($last->text);
                $contents = substr($contents, 0, $after) . ',' . substr($contents, $after);
                $close++;
            }

            $closeIndent = self::lineIndent($contents, $close);
            $before = rtrim(substr($contents, 0, $close));

            return $before . "\n" . self::entry($closeIndent . '    ', $chain) . "\n" . $closeIndent . substr($contents, $close);
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @param  list<string>  $chain
     */
    protected static function appendPluginCall(string $contents, array $tokens, array $chain): ?string
    {
        $statement = self::returnStatement($tokens);

        if ($statement === null) {
            return null;
        }

        $semicolon = $tokens[$statement[1]]->pos;
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

        return preg_replace('/^<\?php\s*\n/', "<?php\n\n{$import}\n\n", $contents, 1) ?? $contents;
    }

    /**
     * Removes the registration whose class name is the token at $index, or
     * returns null when it is not in a shape that can be cut safely.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected static function removeRegistration(string $contents, array $tokens, int $index): ?string
    {
        $endIndex = self::closingIndex($tokens, $index, [',', ';', ...self::CLOSERS]);

        if ($endIndex === null || $index < 1) {
            return null;
        }

        // ->plugin(Plugin::make()...): the whole call goes.
        if ($index >= 3 && self::isMethodCall($tokens, $index - 2, 'plugin')) {
            $closeIndex = self::closingIndex($tokens, $index, [')']);

            return $closeIndex === null ? null : self::removeCall($contents, $tokens, $index - 3, $closeIndex);
        }

        $end = $tokens[$endIndex];

        if (! in_array($end->text, [',', ']', ')'], true)) {
            return null;
        }

        $openIndex = self::openingIndex($tokens, $index);
        $pluginsCall = $openIndex !== null && $openIndex >= 3 && $tokens[$openIndex]->text === '[' && self::isMethodCall($tokens, $openIndex - 2, 'plugins')
            ? $tokens[$openIndex - 3]->pos
            : null;

        $start = $tokens[$index]->pos;

        if ($end->text === ',') {
            $entryEnd = $end->pos + 1;
        } else {
            $last = $tokens[$endIndex - 1];
            $entryEnd = $last->pos + strlen($last->text);
        }

        $lineStart = strrpos(substr($contents, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($contents, "\n", $entryEnd);
        $lineEnd = $lineEnd === false ? strlen($contents) : $lineEnd + 1;
        $rest = trim(substr($contents, $entryEnd, $lineEnd - $entryEnd));

        if (trim(substr($contents, $lineStart, $start - $lineStart)) === '' && ($rest === '' || preg_match('~^(//|#(?!\[))~', $rest))) {
            // An entry on lines of its own takes those lines, and its trailing comment, with it.
            [$start, $entryEnd] = [$lineStart, $lineEnd];
        } elseif ($end->text === ',') {
            $entryEnd += strspn($contents, " \t", $entryEnd);
        } else {
            // The last entry, on the line of the previous one: the comma separating them goes too.
            $before = rtrim(substr($contents, 0, $start), " \t");

            if (str_ends_with($before, ',')) {
                $start = strlen($before) - 1;
            }
        }

        $contents = substr($contents, 0, $start) . substr($contents, $entryEnd);

        return $pluginsCall === null ? $contents : self::removeEmptyPluginsCall($contents, $pluginsCall);
    }

    protected static function removeEmptyPluginsCall(string $contents, int $position): string
    {
        $tokens = self::tokens($contents);

        foreach ($tokens as $i => $token) {
            if ($token->pos === $position
                && self::isMethodCall($tokens, $i + 1, 'plugins')
                && ($tokens[$i + 3] ?? null)?->text === '['
                && ($tokens[$i + 4] ?? null)?->text === ']'
                && ($tokens[$i + 5] ?? null)?->text === ')') {
                return self::removeCall($contents, $tokens, $i, $i + 5);
            }
        }

        return $contents;
    }

    /**
     * Cuts the `->method(...)` call from the arrow at $arrowIndex to the
     * closing parenthesis at $closeIndex.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected static function removeCall(string $contents, array $tokens, int $arrowIndex, int $closeIndex): string
    {
        $callStart = $tokens[$arrowIndex]->pos;
        $callEnd = $tokens[$closeIndex]->pos + 1;
        $previous = $tokens[$arrowIndex - 1];
        $previousEnd = $previous->pos + strlen($previous->text);

        // Nothing but whitespace since the previous call: cut from its end, so the chain closes up.
        if (trim(substr($contents, $previousEnd, $callStart - $previousEnd)) === '') {
            return substr($contents, 0, $previousEnd) . substr($contents, $callEnd);
        }

        // A comment sits in between and must keep its line: drop the call's own lines only.
        $lineStart = strrpos(substr($contents, 0, $callStart), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($contents, "\n", $callEnd);
        $lineEnd = $lineEnd === false ? strlen($contents) : $lineEnd + 1;

        if (trim(substr($contents, $lineStart, $callStart - $lineStart)) === '' && trim(substr($contents, $callEnd, $lineEnd - $callEnd)) === '') {
            return substr($contents, 0, $lineStart) . substr($contents, $lineEnd);
        }

        return substr($contents, 0, $callStart) . substr($contents, $callEnd);
    }

    /**
     * Indexes of the class-name tokens of every `Plugin::make(` call that
     * resolves to this package's plugin class.
     *
     * @param  list<PhpToken>  $tokens
     * @return list<int>
     */
    protected static function registrations(array $tokens): array
    {
        $isPlugin = self::classResolver($tokens);
        $found = [];

        foreach ($tokens as $i => $token) {
            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                && ($tokens[$i + 1] ?? null)?->is(T_DOUBLE_COLON)
                && strcasecmp($tokens[$i + 2]->text ?? '', 'make') === 0
                && ($tokens[$i + 3] ?? null)?->text === '('
                && $isPlugin($token->text)) {
                $found[] = $i;
            }
        }

        return $found;
    }

    /**
     * Resolves a class name against the file's namespace and `use` imports
     * and tells whether it is this package's plugin class.
     *
     * @param  list<PhpToken>  $tokens
     * @return Closure(string): bool
     */
    protected static function classResolver(array $tokens): Closure
    {
        $namespace = '';
        $imports = [];
        $depth = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = self::punctuation($token);

            if (in_array($text, ['{', '${'], true)) {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
            } elseif ($token->is(T_NAMESPACE) && ($tokens[$i + 1] ?? null)?->is([T_STRING, T_NAME_QUALIFIED])) {
                $namespace = $tokens[$i + 1]->text;
            } elseif ($token->is(T_USE) && $depth === 0) {
                // A file-level import; trait and closure `use` sit inside braces.
                $end = $i;

                while ($end < $count && $tokens[$end]->text !== ';') {
                    $end++;
                }

                $imports += self::parseImport(array_slice($tokens, $i + 1, $end - $i - 1));
                $i = $end;
            }
        }

        return function (string $name) use ($namespace, $imports): bool {
            if (str_starts_with($name, '\\')) {
                $class = substr($name, 1);
            } else {
                $segments = explode('\\', $name);
                $alias = strtolower($segments[0]);
                $class = isset($imports[$alias])
                    ? implode('\\', [$imports[$alias], ...array_slice($segments, 1)])
                    : ltrim($namespace . '\\' . $name, '\\');
            }

            return strcasecmp($class, InfinitoOnboardingPlugin::class) === 0;
        };
    }

    /**
     * Maps each lowercased alias of a `use A\B, C\D as E` clause to its class.
     * Function, const and grouped imports are ignored.
     *
     * @param  list<PhpToken>  $clause
     * @return array<string, string>
     */
    protected static function parseImport(array $clause): array
    {
        if ($clause === [] || $clause[0]->is([T_FUNCTION, T_CONST]) || in_array('{', array_map(fn (PhpToken $token): string => $token->text, $clause), true)) {
            return [];
        }

        $imports = [];
        $parts = [[]];

        foreach ($clause as $token) {
            if ($token->text === ',') {
                $parts[] = [];
            } else {
                $parts[array_key_last($parts)][] = $token;
            }
        }

        foreach ($parts as $part) {
            if ($part === []) {
                continue;
            }

            $class = ltrim($part[0]->text, '\\');
            $alias = isset($part[1], $part[2]) && $part[1]->is(T_AS) ? $part[2]->text : class_basename($class);
            $imports[strtolower($alias)] = $class;
        }

        return $imports;
    }

    /**
     * Index of the `return $panel` token and of the `;` ending that statement.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}|null
     */
    protected static function returnStatement(array $tokens): ?array
    {
        foreach ($tokens as $i => $token) {
            if ($token->is(T_RETURN) && ($tokens[$i + 1] ?? null)?->is(T_VARIABLE) && $tokens[$i + 1]->text === '$panel') {
                $semicolon = self::closingIndex($tokens, $i + 1, [';']);

                return $semicolon === null ? null : [$i, $semicolon];
            }
        }

        return null;
    }

    /**
     * Whether $tokens[$index] is the method name of a `->$name(` call.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected static function isMethodCall(array $tokens, int $index, string $name): bool
    {
        return ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
            && ($tokens[$index] ?? null)?->is(T_STRING)
            && strcasecmp($tokens[$index]->text, $name) === 0
            && ($tokens[$index + 1] ?? null)?->text === '(';
    }

    /**
     * Index of the first of $targets found at the nesting level of $from, or
     * null when the enclosing bracket closes first.
     *
     * @param  list<PhpToken>  $tokens
     * @param  list<string>  $targets
     */
    protected static function closingIndex(array $tokens, int $from, array $targets): ?int
    {
        $depth = 0;
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            $text = self::punctuation($tokens[$i]);

            if ($depth === 0 && in_array($text, $targets, true)) {
                return $i;
            }

            if (in_array($text, self::OPENERS, true)) {
                $depth++;
            } elseif (in_array($text, self::CLOSERS, true) && --$depth < 0) {
                return null;
            }
        }

        return null;
    }

    /**
     * Index of the bracket enclosing the token at $index.
     *
     * @param  list<PhpToken>  $tokens
     */
    protected static function openingIndex(array $tokens, int $index): ?int
    {
        $depth = 0;

        for ($i = $index - 1; $i >= 0; $i--) {
            $text = self::punctuation($tokens[$i]);

            if (in_array($text, self::CLOSERS, true)) {
                $depth++;
            } elseif (in_array($text, self::OPENERS, true) && $depth-- === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The token's text when it is a bracket or separator, so a `]` inside an
     * interpolated string never counts as one.
     */
    protected static function punctuation(PhpToken $token): ?string
    {
        return $token->id < 256 || $token->is([T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])
            ? $token->text
            : null;
    }

    /**
     * @return list<PhpToken>
     */
    protected static function tokens(string $contents): array
    {
        return array_values(array_filter(
            PhpToken::tokenize($contents),
            fn (PhpToken $token): bool => ! $token->is(self::IGNORED),
        ));
    }

    protected static function lineIndent(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, $offset), "\n");
        $from = $lineStart === false ? 0 : $lineStart + 1;
        $line = substr($contents, $from, $offset - $from);

        return (string) preg_replace('/\S.*$/s', '', $line);
    }
}
