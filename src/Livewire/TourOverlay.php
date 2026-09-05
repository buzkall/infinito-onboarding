<?php

namespace Arzcode\InfinitoOnboarding\Livewire;

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Arzcode\InfinitoOnboarding\Support\TourAnalytics;
use Arzcode\InfinitoOnboarding\Support\TourResolver;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Renders the tour payload for the Alpine `infinitoOnboardingTour` component
 * and persists the seen-state when the user completes or dismisses it.
 */
class TourOverlay extends Component
{
    /** The tour to show. Null resolves it on mount from the current request. */
    #[Locked]
    public ?int $tourId = null;

    /** Preview mode: forced by an authorised user, never persists seen-state. */
    #[Locked]
    public bool $preview = false;

    /** The page path captured on first render (later Livewire requests hit /livewire/update). */
    #[Locked]
    public ?string $path = null;

    #[Locked]
    public ?string $tenantId = null;

    public function mount(): void
    {
        $this->path ??= request()->path();
        $this->tenantId ??= static::currentTenantId();

        if ($this->tourId === null) {
            $plugin = static::plugin();
            $this->preview = $plugin !== null && static::isPreviewRequest($plugin, request());
            $this->tourId = static::resolveTour($this->user(), $this->path, $this->tenantId, $this->preview, static::previewKey(request()))?->id;
        }
    }

    public function getTour(): ?Tour
    {
        if ($this->tourId === null) {
            return null;
        }

        return Tour::query()->with('steps')->find($this->tourId);
    }

    public function markCompleted(): void
    {
        $this->persist(completed: true);
        $this->record(TourEventType::Completed);
    }

    public function markDismissed(): void
    {
        $this->persist(completed: false);
        $this->record(TourEventType::Dismissed);
    }

    /**
     * Hint mode: the user dismissed one beacon. When every hint of the tour
     * has been dismissed the tour counts as completed.
     */
    public function dismissHint(int $stepId): void
    {
        $tour = $this->getTour();
        $user = $this->user();

        if ($tour === null || $user === null || $this->preview || ! $tour->isHint()) {
            return;
        }

        if (! $tour->steps->contains('id', $stepId)) {
            return;
        }

        $attributes = [
            'tour_id' => $tour->id,
            'user_id' => (string) $user->getAuthIdentifier(),
            'tenant_id' => TourCompletion::normalizeTenantId($this->tenantId),
            'seen_version' => $tour->version,
        ];

        try {
            /** @var TourCompletion $completion */
            $completion = TourCompletion::query()->firstOrCreate($attributes);
        } catch (UniqueConstraintViolationException) {
            /** @var TourCompletion $completion */
            $completion = TourCompletion::query()->where($attributes)->firstOrFail();
        }

        if (in_array($stepId, $completion->getDismissedStepIds(), true)) {
            return;
        }

        $dismissed = array_values(array_unique([...$completion->getDismissedStepIds(), $stepId]));
        $remaining = $tour->steps->pluck('id')->map(fn ($id): int => (int) $id)->diff($dismissed);

        $completion->meta = [...($completion->meta ?? []), 'dismissed_steps' => $dismissed];

        if ($remaining->isEmpty()) {
            $completion->completed_at ??= now();
        }

        $completion->save();

        $this->record(TourEventType::Step, ['step_id' => $stepId, 'hint_dismissed' => true]);

        if ($remaining->isEmpty()) {
            $this->record(TourEventType::Completed);
        }
    }

    /**
     * Analytics reported by the browser: `view`, `step` (with step_id /
     * index) and `target_missing` (with selector). Completed / dismissed are
     * recorded server-side by the mark* actions.
     *
     * @param  array<string, mixed>  $meta
     */
    public function track(string $event, array $meta = []): void
    {
        if (! in_array($event, TourEventType::reportable(), true)) {
            return;
        }

        $this->record(TourEventType::from($event), $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function record(TourEventType $type, array $meta = []): void
    {
        if (! TourAnalytics::isEnabled() || $this->preview) {
            return;
        }

        $tour = $this->getTour();
        $user = $this->user();

        if ($tour === null || $user === null) {
            return;
        }

        $stepId = isset($meta['step_id']) && is_numeric($meta['step_id']) ? (int) $meta['step_id'] : null;

        if ($stepId !== null && ! $tour->steps->contains('id', $stepId)) {
            $stepId = null;
        }

        $meta = collect($meta)
            ->only(['index', 'selector', 'step_title', 'target_type', 'target', 'hint_dismissed'])
            ->map(fn (mixed $value): mixed => is_scalar($value) ? (is_string($value) ? mb_substr($value, 0, 500) : $value) : null)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();

        TourEvent::query()->create([
            'tour_id' => $tour->id,
            'step_id' => $stepId,
            'user_id' => (string) $user->getAuthIdentifier(),
            'tenant_id' => TourCompletion::normalizeTenantId($this->tenantId),
            'version' => $tour->version,
            'event' => $type,
            'meta' => $meta === [] ? null : $meta,
            'created_at' => now(),
        ]);
    }

    protected function persist(bool $completed): void
    {
        $tour = $this->getTour();
        $user = $this->user();

        if ($tour === null || $user === null || $this->preview) {
            return;
        }

        $attributes = [
            'tour_id' => $tour->id,
            'user_id' => (string) $user->getAuthIdentifier(),
            'tenant_id' => TourCompletion::normalizeTenantId($this->tenantId),
            'seen_version' => $tour->version,
        ];

        $values = $completed
            ? ['completed_at' => now()]
            : ['dismissed_at' => now()];

        try {
            TourCompletion::query()->updateOrCreate($attributes, $values);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request already stored the seen-state; nothing to do.
        }
    }

    public function render(): View
    {
        $tour = $this->getTour();

        /** @var view-string $view */
        $view = 'infinito-onboarding::livewire.tour-overlay';

        $payload = $tour ? static::payloadFor($tour) : null;

        if ($tour?->isHint() && $payload !== null && ($user = $this->user()) !== null) {
            $payload['dismissed_steps'] = $tour->completionFor($user->getAuthIdentifier(), $this->tenantId)?->getDismissedStepIds() ?? [];
        }

        return view($view, [
            'tour' => $tour,
            'payload' => $payload,
            'labels' => static::labels(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers shared with the render hook
    |--------------------------------------------------------------------------
    */

    public static function resolveTourForRequest(InfinitoOnboardingPlugin $plugin, Request $request): ?Tour
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            return null;
        }

        return static::resolveTour(
            $user,
            $request->path(),
            static::currentTenantId(),
            static::isPreviewRequest($plugin, $request),
            static::previewKey($request),
        );
    }

    public static function resolveTour(
        ?Authenticatable $user,
        string $path,
        ?string $tenantId,
        bool $preview = false,
        ?string $previewKey = null,
    ): ?Tour {
        if ($user === null) {
            return null;
        }

        /** @var TourResolver $resolver */
        $resolver = app(TourResolver::class);

        if ($preview) {
            return $resolver->resolveForPreview($user, $path, $tenantId, $previewKey);
        }

        return $resolver->resolveFor($user, $path);
    }

    public static function isPreviewRequest(InfinitoOnboardingPlugin $plugin, Request $request): bool
    {
        $parameter = static::previewParameter();

        if (! $request->filled($parameter)) {
            return false;
        }

        return $plugin->isAuthorized();
    }

    public static function previewKey(Request $request): ?string
    {
        $value = $request->query(static::previewParameter());

        if (! is_string($value) || $value === '' || in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)) {
            return null;
        }

        return $value;
    }

    public static function previewParameter(): string
    {
        return (string) config('infinito-onboarding.query_parameters.preview', 'onboarding-preview');
    }

    public static function currentTenantId(): ?string
    {
        $tenant = Filament::getTenant();

        return $tenant?->getKey() !== null ? (string) $tenant->getKey() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFor(Tour $tour): array
    {
        return [
            'tour' => [
                'id' => $tour->id,
                'key' => $tour->key,
                'name' => $tour->translated('name'),
                'mode' => $tour->mode->value,
                'version' => $tour->version,
            ],
            'steps' => $tour->steps->map->toPayload()->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'next' => __('infinito-onboarding::onboarding.overlay.next'),
            'previous' => __('infinito-onboarding::onboarding.overlay.previous'),
            'done' => __('infinito-onboarding::onboarding.overlay.done'),
            'progress' => __('infinito-onboarding::onboarding.overlay.progress', ['current' => '{{current}}', 'total' => '{{total}}']),
            'got_it' => __('infinito-onboarding::onboarding.hints.got_it'),
            'dismiss_all' => __('infinito-onboarding::onboarding.hints.dismiss_all'),
            'open_hint' => __('infinito-onboarding::onboarding.hints.open'),
        ];
    }

    protected function user(): ?Authenticatable
    {
        return Filament::auth()->user();
    }

    protected static function plugin(): ?InfinitoOnboardingPlugin
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('infinito-onboarding')) {
            return null;
        }

        /** @var InfinitoOnboardingPlugin $plugin */
        $plugin = $panel->getPlugin('infinito-onboarding');

        return $plugin;
    }
}
