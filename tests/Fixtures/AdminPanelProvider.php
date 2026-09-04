<?php

namespace Arzcode\InfinitoOnboarding\Tests\Fixtures;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->pages([])
            ->plugin(InfinitoOnboardingPlugin::make());
    }
}
