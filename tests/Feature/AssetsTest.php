<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

it('registers the bundled script and stylesheet under the package name', function (): void {
    $scripts = FilamentAsset::getScripts([InfinitoOnboardingServiceProvider::$assetPackage]);
    $styles = FilamentAsset::getStyles([InfinitoOnboardingServiceProvider::$assetPackage]);

    expect($scripts)->toHaveCount(1)
        ->and($scripts[0])->toBeInstanceOf(Js::class)
        ->and($scripts[0]->getId())->toBe('infinito-onboarding')
        ->and($styles)->toHaveCount(1)
        ->and($styles[0])->toBeInstanceOf(Css::class)
        ->and($styles[0]->getId())->toBe('infinito-onboarding');
});

it('ships committed dist bundles that embed driver.js instead of loading a CDN', function (): void {
    $js = file_get_contents(__DIR__ . '/../../resources/dist/infinito-onboarding.js');
    $css = file_get_contents(__DIR__ . '/../../resources/dist/infinito-onboarding.css');

    expect($js)->toContain('infinitoOnboardingTour')
        ->and($js)->toContain('driver-popover')
        ->and($js)->not->toContain('cdn.jsdelivr.net')
        ->and($js)->not->toContain('unpkg.com')
        ->and($css)->toContain('.driver-popover')
        ->and($css)->toContain('io-popover');
});
