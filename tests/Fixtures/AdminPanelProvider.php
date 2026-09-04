<?php

namespace Arzcode\InfinitoOnboarding\Tests\Fixtures;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Filament\Pages\Dashboard;
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
            ->pages([Dashboard::class])
            ->plugin(
                InfinitoOnboardingPlugin::make()
                    ->resource()
                    ->authorize(fn (User $user): bool => in_array('tour-author', $user->roles ?? [], true)),
            );
    }
}
