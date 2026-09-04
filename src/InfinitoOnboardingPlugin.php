<?php

namespace Arzcode\InfinitoOnboarding;

use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;

class InfinitoOnboardingPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure $isEnabled = true;

    protected ?Closure $authorizeUsing = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'infinito-onboarding';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        $this->registerOverlayHook($panel);
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    /**
     * Switch the whole overlay on or off (e.g. `->enabled(fn () => ! app()->runningUnitTests())`).
     */
    public function enabled(bool|Closure $condition = true): static
    {
        $this->isEnabled = $condition;

        return $this;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->evaluate($this->isEnabled);
    }

    /**
     * Who may manage tours: preview them regardless of seen-state, use record
     * mode and access the TourResource. Receives the authenticated user.
     *
     * Defaults to nobody, so management features are opt-in.
     */
    public function authorize(Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(?Authenticatable $user = null): bool
    {
        $user ??= Filament::auth()->user();

        if ($user === null || $this->authorizeUsing === null) {
            return false;
        }

        return (bool) $this->evaluate($this->authorizeUsing, [
            'user' => $user,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Overlay
    |--------------------------------------------------------------------------
    */

    protected function registerOverlayHook(Panel $panel): void
    {
        $panelId = $panel->getId();

        // Guard against booting the same panel twice within one request. Bound
        // to the container (not a static) so a fresh application, e.g. every
        // test case, registers again.
        $flag = "infinito-onboarding.overlay-hook.{$panelId}";

        if (app()->bound($flag)) {
            return;
        }

        app()->instance($flag, true);

        // Filament's render hook scopes are page classes, not panels, so the
        // closure guards on the current panel id: a plugin registered in two
        // panels never renders twice, and never leaks into a panel that did
        // not register it.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => $this->renderOverlay($panelId),
        );
    }

    public function renderOverlay(string $panelId): string
    {
        if (Filament::getCurrentPanel()?->getId() !== $panelId) {
            return '';
        }

        if (! $this->isEnabled()) {
            return '';
        }

        if (Filament::auth()->guest()) {
            return '';
        }

        $tour = TourOverlay::resolveTourForRequest($this, request());

        if ($tour === null) {
            return '';
        }

        return Blade::render('@livewire($component, $params)', [
            'component' => TourOverlay::class,
            'params' => [
                'tourId' => $tour->id,
                'preview' => TourOverlay::isPreviewRequest($this, request()),
            ],
        ]);
    }
}
