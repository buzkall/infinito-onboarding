<?php

namespace Arzcode\InfinitoOnboarding;

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Arzcode\InfinitoOnboarding\Livewire\ChangelogTrigger;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Support\Segments;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use UnitEnum;

class InfinitoOnboardingPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure $isEnabled = true;

    protected ?Closure $authorizeUsing = null;

    protected bool|Closure $hasResource = false;

    /** @var class-string<TourResource> */
    protected string $resourceClass = TourResource::class;

    protected string|UnitEnum|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool|Closure $hasTopbarTrigger = false;

    protected string $topbarTriggerHook = PanelsRenderHook::GLOBAL_SEARCH_AFTER;

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

    /** @var array<string, array<string, mixed>|Closure> */
    protected array $segments = [];

    public function register(Panel $panel): void
    {
        if ($this->hasResource()) {
            $panel->resources([$this->resourceClass]);
        }

        /** @var Segments $registry */
        $registry = app(Segments::class);

        foreach ($this->segments as $name => $definition) {
            $registry->register($name, $definition);
        }
    }

    /**
     * Define a reusable audience segment tours can target through
     * `audience.segments`: either criteria (`['roles' => [...]]`) or a closure
     * receiving the user.
     *
     * @param  array<string, mixed>|Closure  $definition
     */
    public function segment(string $name, array|Closure $definition): static
    {
        $this->segments[$name] = $definition;

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>|Closure>  $segments
     */
    public function segments(array $segments): static
    {
        foreach ($segments as $name => $definition) {
            $this->segment((string) $name, $definition);
        }

        return $this;
    }

    public function boot(Panel $panel): void
    {
        $this->registerOverlayHook($panel);
        $this->registerTopbarTriggerHook($panel);
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

    /**
     * Register the TourResource in the panel so tours can be managed from the
     * UI. Off by default; call it before passing the plugin to the panel:
     * `InfinitoOnboardingPlugin::make()->resource()`.
     *
     * @param  class-string<TourResource>|null  $resource  A subclass to customise the resource.
     */
    public function resource(bool|Closure $condition = true, ?string $resource = null): static
    {
        $this->hasResource = $condition;

        if ($resource !== null) {
            $this->resourceClass = $resource;
        }

        return $this;
    }

    public function hasResource(): bool
    {
        return (bool) $this->evaluate($this->hasResource);
    }

    /**
     * @return class-string<TourResource>
     */
    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    /**
     * Show a "What's new" button in the topbar with a dot badge while an
     * unseen changelog exists. Users can re-open the latest changelogs from
     * it at any time.
     *
     * @param  string  $hook  The panel render hook to render the button into.
     */
    public function topbarTrigger(bool|Closure $condition = true, string $hook = PanelsRenderHook::GLOBAL_SEARCH_AFTER): static
    {
        $this->hasTopbarTrigger = $condition;
        $this->topbarTriggerHook = $hook;

        return $this;
    }

    public function hasTopbarTrigger(): bool
    {
        return (bool) $this->evaluate($this->hasTopbarTrigger);
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

    protected function registerTopbarTriggerHook(Panel $panel): void
    {
        $panelId = $panel->getId();
        $flag = "infinito-onboarding.topbar-trigger-hook.{$panelId}";

        if (app()->bound($flag)) {
            return;
        }

        app()->instance($flag, true);

        FilamentView::registerRenderHook(
            $this->topbarTriggerHook,
            fn (): string => $this->renderTopbarTrigger($panelId),
        );
    }

    public function renderTopbarTrigger(string $panelId): string
    {
        if (Filament::getCurrentPanel()?->getId() !== $panelId) {
            return '';
        }

        if (! $this->isEnabled() || ! $this->hasTopbarTrigger()) {
            return '';
        }

        if (Filament::auth()->guest()) {
            return '';
        }

        return Blade::render('@livewire($component)', [
            'component' => ChangelogTrigger::class,
        ]);
    }

    protected function renderRecorder(): string
    {
        $tour = TourRecorder::resolveTourForRequest(request());

        if ($tour === null) {
            return '';
        }

        return Blade::render('@livewire($component, $params)', [
            'component' => TourRecorder::class,
            'params' => ['tourId' => $tour->id],
        ]);
    }

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

        if (TourRecorder::isRecordRequest($this, request())) {
            return $this->renderRecorder();
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
