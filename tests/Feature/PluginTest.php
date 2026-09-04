<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Filament\Facades\Filament;

it('boots the admin panel with the plugin registered', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin('infinito-onboarding'))->toBeTrue()
        ->and($panel->getPlugin('infinito-onboarding'))->toBeInstanceOf(InfinitoOnboardingPlugin::class);
});

it('exposes the plugin through the static accessor', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(InfinitoOnboardingPlugin::get())->toBeInstanceOf(InfinitoOnboardingPlugin::class)
        ->and(InfinitoOnboardingPlugin::get()->getId())->toBe('infinito-onboarding');
});

it('loads the package config', function (): void {
    expect(config('infinito-onboarding.table_names.tours'))->toBe('onboarding_tours');
});
