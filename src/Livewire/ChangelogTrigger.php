<?php

namespace Arzcode\InfinitoOnboarding\Livewire;

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Support\TourResolver;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "What's new" button for the topbar: shows a dot badge while an unseen
 * changelog exists and re-opens the latest changelogs on demand.
 */
class ChangelogTrigger extends Component
{
    #[Locked]
    public ?string $tenantId = null;

    /** How many changelogs the modal lists, latest first. */
    #[Locked]
    public int $limit = 5;

    public function mount(): void
    {
        $this->tenantId ??= TourOverlay::currentTenantId();
    }

    /**
     * Published changelogs the user is eligible for, latest first,
     * regardless of seen-state and route.
     *
     * @return Collection<int, Tour>
     */
    public function getChangelogs(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return new Collection;
        }

        /** @var TourResolver $resolver */
        $resolver = app(TourResolver::class);

        return Tour::query()
            ->with('steps')
            ->active()
            ->published()
            ->withinWindow()
            ->forTenant($this->tenantId)
            ->mode(TourMode::Changelog)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Tour $tour): bool => $resolver->passesAudience($tour, $user))
            ->take($this->limit)
            ->values();
    }

    /**
     * @return Collection<int, Tour>
     */
    public function getUnseenChangelogs(): Collection
    {
        $user = $this->user();

        if ($user === null) {
            return new Collection;
        }

        return $this->getChangelogs()
            ->filter(fn (Tour $tour): bool => ! $tour->isSeenBy($user->getAuthIdentifier(), $this->tenantId))
            ->values();
    }

    public function hasUnseen(): bool
    {
        return $this->getUnseenChangelogs()->isNotEmpty();
    }

    /**
     * Mark every listed unseen changelog as seen (completed) for the user.
     */
    public function markCompleted(): void
    {
        $this->persist(completed: true);
    }

    public function markDismissed(): void
    {
        $this->persist(completed: false);
    }

    protected function persist(bool $completed): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        foreach ($this->getUnseenChangelogs() as $tour) {
            try {
                TourCompletion::query()->updateOrCreate([
                    'tour_id' => $tour->id,
                    'user_id' => (string) $user->getAuthIdentifier(),
                    'tenant_id' => TourCompletion::normalizeTenantId($this->tenantId),
                    'seen_version' => $tour->version,
                ], $completed ? ['completed_at' => now()] : ['dismissed_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Already recorded concurrently.
            }
        }
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'infinito-onboarding::livewire.changelog-trigger';

        return view($view, [
            'changelogs' => $this->getChangelogs(),
            'hasUnseen' => $this->hasUnseen(),
        ]);
    }

    protected function user(): ?Authenticatable
    {
        return Filament::auth()->user();
    }
}
