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
