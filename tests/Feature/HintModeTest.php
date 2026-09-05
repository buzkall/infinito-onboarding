<?php

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Support\TourResolver;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('adds the hint mode to the enum and the builder', function (): void {
    expect(TourMode::Hint->value)->toBe('hint')
        ->and(TourMode::Hint->getLabel())->toBe('Hints (beacons)')
        ->and(Tour::define('h')->hints()->step('a', 'A')->save()->isHint())->toBeTrue();
});

it('renders the hints component with the dismissed ids for the user', function (): void {
    $tour = Tour::factory()->hints()->create(['key' => 'beacons']);
    $a = TourStep::factory()->for($tour)->create(['title' => 'First hint']);
    TourStep::factory()->for($tour)->create(['title' => 'Second hint']);
    TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1', 'completed_at' => null, 'meta' => ['dismissed_steps' => [$a->id]]]);

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])
        ->assertSeeHtml('data-tour-hints')
        ->assertSeeHtml('x-data="infinitoOnboardingHints(')
        ->assertSee('First hint')
        ->assertSee('Second hint')
        ->assertSeeHtml("dismissed: JSON.parse('[" . $a->id . "]')")
        ->assertDontSeeHtml('infinitoOnboardingTour(');
});

it('keeps resolving a hint tour while some hints remain and stops once all are dismissed', function (): void {
    $tour = Tour::factory()->hints()->create();
    $a = TourStep::factory()->for($tour)->create();
    $b = TourStep::factory()->for($tour)->create();
    $resolver = app(TourResolver::class);

    $component = Livewire::test(TourOverlay::class, ['tourId' => $tour->id]);

    $component->call('dismissHint', $a->id);

    $completion = TourCompletion::first();

    expect($completion->getDismissedStepIds())->toBe([$a->id])
        ->and($completion->isFinished())->toBeFalse()
        ->and($tour->isSeenBy($this->user->id))->toBeFalse()
        ->and($resolver->resolveFor($this->user, 'admin')?->id)->toBe($tour->id);

    $component->call('dismissHint', $a->id);
    $component->call('dismissHint', 999999);

    expect(TourCompletion::count())->toBe(1)
        ->and(TourCompletion::first()->getDismissedStepIds())->toBe([$a->id]);

    $component->call('dismissHint', $b->id);

    $completion->refresh();

    expect($completion->getDismissedStepIds())->toBe([$a->id, $b->id])
        ->and($completion->completed_at)->not->toBeNull()
        ->and($tour->isSeenBy($this->user->id))->toBeTrue()
        ->and($resolver->resolveFor($this->user, 'admin'))->toBeNull()
        ->and(TourEvent::query()->ofType(TourEventType::Completed)->count())->toBe(1)
        ->and(TourEvent::query()->ofType(TourEventType::Step)->count())->toBe(2);
});

it('ignores dismissHint for non-hint tours and in preview mode', function (): void {
    $tour = Tour::factory()->create();
    $step = TourStep::factory()->for($tour)->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('dismissHint', $step->id);

    $hints = Tour::factory()->hints()->create();
    $hint = TourStep::factory()->for($hints)->create();

    Livewire::test(TourOverlay::class, ['tourId' => $hints->id, 'preview' => true])->call('dismissHint', $hint->id);

    expect(TourCompletion::count())->toBe(0);
});

it('lets "dismiss all" complete a hint tour through markCompleted', function (): void {
    $tour = Tour::factory()->hints()->create();
    TourStep::factory()->for($tour)->count(2)->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markCompleted');

    expect($tour->isSeenBy($this->user->id))->toBeTrue();
});

it('re-shows every hint after a version bump', function (): void {
    $tour = Tour::factory()->hints()->version('1')->create();
    $a = TourStep::factory()->for($tour)->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('dismissHint', $a->id);

    expect($tour->isSeenBy($this->user->id))->toBeTrue();

    $tour->update(['version' => '2']);

    expect($tour->isSeenBy($this->user->id))->toBeFalse()
        ->and($tour->completionFor($this->user->id))->toBeNull();
});
