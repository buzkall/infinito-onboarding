<?php

use Arzcode\InfinitoOnboarding\Support\PanelProviderPatcher;

function panelProvider(string $chain): string
{
    return <<<PHP
    <?php

    namespace App\Providers\Filament;

    use Filament\Panel;
    use Filament\PanelProvider;

    class AdminPanelProvider extends PanelProvider
    {
        public function panel(Panel \$panel): Panel
        {
            return \$panel
    {$chain};
        }
    }

    PHP;
}

function assertValidPhp(string $code): void
{
    PhpToken::tokenize($code, TOKEN_PARSE);

    expect(true)->toBeTrue();
}

it('adds the plugin to an existing plugins array', function (): void {
    $original = panelProvider(<<<'PHP'
                ->id('admin')
                ->plugins([
                    FooPlugin::make()
                        ->label('a, b [c]'),

                    BarPlugin::make()
                ])
    PHP);

    $patched = PanelProviderPatcher::add($original, ['->resource()', '->authorize(fn (): bool => true)']);

    expect($patched)
        ->toContain("use Filament\\PanelProvider;\nuse Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\n")
        ->toContain(<<<'PHP'
                    BarPlugin::make(),
                    InfinitoOnboardingPlugin::make()
                        ->resource()
                        ->authorize(fn (): bool => true),
                ]);
    PHP);

    assertValidPhp($patched);
});

it('adds a plugin call when the panel has no plugins array', function (): void {
    $patched = PanelProviderPatcher::add(panelProvider(<<<'PHP'
                ->id('admin')
                ->path('admin')
    PHP), ['->resource()']);

    expect($patched)->toContain(<<<'PHP'
                ->path('admin')
                ->plugin(
                    InfinitoOnboardingPlugin::make()
                        ->resource(),
                );
    PHP);

    assertValidPhp($patched);
});

it('returns null when the provider has no panel chain', function (): void {
    expect(PanelProviderPatcher::add("<?php\n\nclass Foo {}\n"))->toBeNull();
});

it('restores the original provider when removing what it added', function (string $chain, ?string $expected = null): void {
    $original = panelProvider($chain);
    $patched = PanelProviderPatcher::add($original, ['->resource()', '->authorize(fn (): bool => app()->isLocal())']);

    expect(PanelProviderPatcher::contains($patched))->toBeTrue()
        ->and(PanelProviderPatcher::remove($patched))->toBe(panelProvider($expected ?? $chain));
})->with([
    'plugins array' => [<<<'PHP'
                ->id('admin')
                ->plugins([
                    FooPlugin::make(),
                ])
    PHP],
    'plugins array without trailing comma' => [<<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                    BarPlugin::make()
                ])
    PHP, <<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                    BarPlugin::make(),
                ])
    PHP],
    'no plugins' => [<<<'PHP'
                ->id('admin')
                ->path('admin')
    PHP],
]);

it('removes a hand-written registration', function (string $chain, string $expected): void {
    $patched = PanelProviderPatcher::remove(
        str_replace("use Filament\\Panel;\n", "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\nuse Filament\\Panel;\n", panelProvider($chain)),
    );

    expect($patched)->toBe(panelProvider($expected))
        ->and(PanelProviderPatcher::contains($patched))->toBeFalse();

    assertValidPhp($patched);
})->with([
    'plugin call in the middle of the chain' => [<<<'PHP'
                ->id('admin')
                ->plugin(
                    InfinitoOnboardingPlugin::make()
                        ->authorize(fn (User $user): bool => in_array('admin', $user->roles ?? [], true)),
                )
                ->path('admin')
    PHP, <<<'PHP'
                ->id('admin')
                ->path('admin')
    PHP],
    'first array item' => [<<<'PHP'
                ->plugins([
                    InfinitoOnboardingPlugin::make()->resource(), // tours
                    FooPlugin::make(),
                ])
    PHP, <<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                ])
    PHP],
    'last array item without trailing comma' => [<<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                    InfinitoOnboardingPlugin::make()
                        ->navigationGroup('Settings')
                ])
    PHP, <<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                ])
    PHP],
    'inline array' => [<<<'PHP'
                ->plugins([FooPlugin::make(), InfinitoOnboardingPlugin::make(), BarPlugin::make()])
    PHP, <<<'PHP'
                ->plugins([FooPlugin::make(), BarPlugin::make()])
    PHP],
]);

it('removes registrations written with a qualified name, an alias or inline', function (string $chain, string $expected, string $imports = ''): void {
    $patched = PanelProviderPatcher::remove(
        str_replace("use Filament\\Panel;\n", $imports . "use Filament\\Panel;\n", panelProvider($chain)),
    );

    expect($patched)->toBe(panelProvider($expected))
        ->and(PanelProviderPatcher::parses($patched))->toBeTrue();
})->with([
    'fully qualified plugin call' => [<<<'PHP'
                ->plugin(\Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin::make())
                ->path('admin')
    PHP, <<<'PHP'
                ->path('admin')
    PHP],
    'aliased import' => [<<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                    Onboarding::make()->resource(),
                ])
    PHP, <<<'PHP'
                ->plugins([
                    FooPlugin::make(),
                ])
    PHP, "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin as Onboarding;\n"],
    'only plugin in the array' => [<<<'PHP'
                ->id('admin')
                ->plugins([
                    InfinitoOnboardingPlugin::make(),
                ])
    PHP, <<<'PHP'
                ->id('admin')
    PHP, "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\n"],
    'inline plugin call' => [<<<'PHP'
                ->id('admin')->plugin(InfinitoOnboardingPlugin::make()->resource())->path('admin')
    PHP, <<<'PHP'
                ->id('admin')->path('admin')
    PHP, "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\n"],
]);

it('resolves a class name relative to an imported namespace', function (): void {
    $original = str_replace("use Filament\\Panel;\n", "use Arzcode\\InfinitoOnboarding;\nuse Filament\\Panel;\n", panelProvider(<<<'PHP'
                ->plugins([FooPlugin::make(), InfinitoOnboarding\InfinitoOnboardingPlugin::make()])
    PHP));

    expect(PanelProviderPatcher::remove($original))
        ->toBe(str_replace('FooPlugin::make(), InfinitoOnboarding\InfinitoOnboardingPlugin::make()', 'FooPlugin::make()', $original));
});

it('keeps a comment ending the line before a removed plugin call', function (): void {
    $patched = PanelProviderPatcher::remove(str_replace("use Filament\\Panel;\n", "use Arzcode\\InfinitoOnboarding\\InfinitoOnboardingPlugin;\nuse Filament\\Panel;\n", panelProvider(<<<'PHP'
                ->path('admin') // keep me
                ->plugin(InfinitoOnboardingPlugin::make())
    PHP)));

    expect($patched)->toContain("->path('admin') // keep me\n            ;")
        ->and(PanelProviderPatcher::parses($patched))->toBeTrue();
});

it('never touches another plugin, even one with a similar name', function (string $imports, string $chain): void {
    $original = str_replace("use Filament\\Panel;\n", $imports . "use Filament\\Panel;\n", panelProvider($chain));

    expect(PanelProviderPatcher::contains($original))->toBeFalse()
        ->and(PanelProviderPatcher::remove($original))->toBe($original);
})->with([
    'subclass' => ['', <<<'PHP'
                ->plugins([
                    CustomInfinitoOnboardingPlugin::make(),
                ])
    PHP],
    'namesake from another namespace' => ["use Acme\\InfinitoOnboardingPlugin;\n", <<<'PHP'
                ->plugins([
                    InfinitoOnboardingPlugin::make(),
                ])
    PHP],
    'commented out' => ['', <<<'PHP'
                ->plugins([
                    // \Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin::make(),
                    FooPlugin::make(),
                ])
    PHP],
]);

it('ignores brackets and commas inside comments and strings when adding', function (): void {
    $patched = PanelProviderPatcher::add(panelProvider(<<<'PHP'
                ->plugins([
                    /* ]); */
                    FooPlugin::make('a], b;') # ']'
                        ->label("x [{$y}]") // ]
                ])
    PHP));

    expect($patched)->toContain(<<<'PHP'
                    FooPlugin::make('a], b;') # ']'
                        ->label("x [{$y}]"), // ]
                    InfinitoOnboardingPlugin::make(),
                ]);
    PHP)
        ->and(PanelProviderPatcher::parses((string) $patched))->toBeTrue()
        ->and(PanelProviderPatcher::contains((string) $patched))->toBeTrue();
});

it('only injects into the plugins array of the returned panel', function (): void {
    $patched = (string) PanelProviderPatcher::add(str_replace(
        'return $panel',
        "\$other = \$something->plugins([BarPlugin::make()]);\n\n        return \$panel",
        panelProvider("            ->id('admin')"),
    ));

    expect($patched)
        ->toContain('$other = $something->plugins([BarPlugin::make()]);')
        ->toContain("->plugin(\n                InfinitoOnboardingPlugin::make(),\n            );")
        ->and(PanelProviderPatcher::parses($patched))->toBeTrue();
});

it('tells whether the code parses', function (): void {
    expect(PanelProviderPatcher::parses(panelProvider("            ->id('admin')")))->toBeTrue()
        ->and(PanelProviderPatcher::parses(panelProvider('            ->plugins([FooPlugin::make() BarPlugin::make()])')))->toBeFalse();
});
