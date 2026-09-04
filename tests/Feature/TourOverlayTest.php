<?php

use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the tour payload when a tour resolves', function (): void {
    $tour = Tour::factory()->create(['key' => 'orders-export', 'version' => '2']);
    TourStep::factory()->for($tour)->create(['title' => 'Export your orders', 'target' => 'export-btn']);

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])
        ->assertSet('tourId', $tour->id)
        ->assertSeeHtml('x-data="infinitoOnboardingTour(')
        ->assertSeeHtml('data-tour-key="orders-export"')
        ->assertSee('Export your orders')
        ->assertSee('export-btn');
});

it('resolves the tour itself when mounted without an id', function (): void {
    $tour = Tour::factory()->forRoute('admin*')->create();

    Livewire::withQueryParams([])
        ->test(TourOverlay::class, ['path' => 'admin/orders'])
        ->assertSet('tourId', $tour->id);
});

it('renders nothing but an empty wrapper when no tour resolves', function (): void {
    Livewire::test(TourOverlay::class, ['path' => 'admin'])
        ->assertSet('tourId', null)
        ->assertDontSeeHtml('x-data="infinitoOnboardingTour(');
});

it('markCompleted() writes exactly one completion row stamped with the current version', function (): void {
    $tour = Tour::factory()->create(['version' => '3.1']);

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markCompleted');

    expect(TourCompletion::count())->toBe(1);

    $completion = TourCompletion::first();

    expect($completion->tour_id)->toBe($tour->id)
        ->and($completion->user_id)->toBe((string) $this->user->id)
        ->and($completion->tenant_id)->toBe('')
        ->and($completion->seen_version)->toBe('3.1')
        ->and($completion->completed_at)->not->toBeNull()
        ->and($completion->dismissed_at)->toBeNull();
});

it('markCompleted() twice does not violate the unique constraint', function (): void {
    $tour = Tour::factory()->create();

    $component = Livewire::test(TourOverlay::class, ['tourId' => $tour->id]);
    $component->call('markCompleted');
    $component->call('markCompleted');
    $component->call('markDismissed');

    expect(TourCompletion::count())->toBe(1);
});

it('markDismissed() stamps dismissed_at', function (): void {
    $tour = Tour::factory()->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markDismissed');

    expect(TourCompletion::first()->dismissed_at)->not->toBeNull()
        ->and(TourCompletion::first()->completed_at)->toBeNull();
});

it('does not resolve the same tour again once completed', function (): void {
    $tour = Tour::factory()->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markCompleted');

    Livewire::test(TourOverlay::class, ['path' => 'admin'])->assertSet('tourId', null);
});

it('never persists seen-state in preview mode', function (): void {
    $tour = Tour::factory()->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id, 'preview' => true])->call('markCompleted');

    expect(TourCompletion::count())->toBe(0);
});

it('scopes completions to the given tenant', function (): void {
    $tour = Tour::factory()->create();

    Livewire::test(TourOverlay::class, ['tourId' => $tour->id, 'tenantId' => 'acme'])->call('markCompleted');

    expect(TourCompletion::first()->tenant_id)->toBe('acme');
});

it('ignores unauthenticated users', function (): void {
    auth()->logout();
    Tour::factory()->create();

    Livewire::test(TourOverlay::class, ['path' => 'admin'])->assertSet('tourId', null);
});

describe('preview flag', function (): void {
    it('is ignored for users that are not authorised', function (): void {
        $tour = Tour::factory()->create();
        TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

        Livewire::withQueryParams(['onboarding-preview' => '1'])
            ->test(TourOverlay::class, ['path' => 'admin'])
            ->assertSet('tourId', null)
            ->assertSet('preview', false);
    });

    it('forces the resolved tour for authorised users regardless of seen-state', function (): void {
        $this->user->update(['roles' => ['tour-author']]);
        $tour = Tour::factory()->create();
        TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

        Livewire::withQueryParams(['onboarding-preview' => '1'])
            ->test(TourOverlay::class, ['path' => 'admin'])
            ->assertSet('tourId', $tour->id)
            ->assertSet('preview', true);
    });

    it('loads a specific draft tour by key for authorised users', function (): void {
        $this->user->update(['roles' => ['tour-author']]);
        $draft = Tour::factory()->unpublished()->forRoute('somewhere/else')->create(['key' => 'draft']);

        Livewire::withQueryParams(['onboarding-preview' => 'draft'])
            ->test(TourOverlay::class, ['path' => 'admin'])
            ->assertSet('tourId', $draft->id)
            ->assertSet('preview', true);
    });
});
