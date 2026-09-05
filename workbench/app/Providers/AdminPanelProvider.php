<?php

namespace Workbench\App\Providers;

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Filament\Pages\OrdersDemo;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Indigo])
            ->brandName('Acme Orders')
            ->userMenu(false)
            ->pages([Dashboard::class, OrdersDemo::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->plugin(
                InfinitoOnboardingPlugin::make()
                    ->resource()
                    ->topbarTrigger()
                    ->authorize(fn (User $user): bool => true),
            );
    }

    public function register(): void
    {
        parent::register();

        config()->set('auth.providers.users.model', User::class);

        // Keep the workbench database inside the package (portable across
        // machines; the file is git-ignored).
        $database = dirname(__DIR__, 2) . '/database/database.sqlite';

        if (! file_exists($database)) {
            touch($database);
        }

        config()->set('database.connections.sqlite.database', $database);
        config()->set('database.default', 'sqlite');
    }
}
