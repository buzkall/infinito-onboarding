<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('injects the overlay at the end of the panel body when a tour resolves', function (): void {
    $tour = Tour::factory()->forRoute('admin*')->create(['key' => 'welcome']);
    TourStep::factory()->for($tour)->create(['title' => 'Say hello']);

    $this->actingAs($this->user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-tour-overlay', escape: false)
        ->assertSee('data-tour-key="welcome"', escape: false)
        ->assertSee('Say hello');
});

it('renders nothing at all when no tour resolves', function (): void {
    Tour::factory()->forRoute('admin/orders')->create();

    $this->actingAs($this->user)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('data-tour-overlay', escape: false)
        ->assertDontSee('infinitoOnboardingTour', escape: false);
});

it('renders nothing for a tour the user already completed', function (): void {
    $tour = Tour::factory()->create();
    TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

    $this->actingAs($this->user)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('data-tour-overlay', escape: false);
});

it('renders nothing when the plugin is disabled', function (): void {
    Tour::factory()->create();
    InfinitoOnboardingPlugin::get()->enabled(false);

    try {
        $this->actingAs($this->user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-tour-overlay', escape: false);
    } finally {
        InfinitoOnboardingPlugin::get()->enabled(true);
    }
});

it('accepts a closure for enabled()', function (): void {
    Tour::factory()->create();
    InfinitoOnboardingPlugin::get()->enabled(fn (): bool => false);

    try {
        $this->actingAs($this->user)->get('/admin')->assertDontSee('data-tour-overlay', escape: false);
    } finally {
        InfinitoOnboardingPlugin::get()->enabled(true);
    }
});

it('loads the bundled script and stylesheet in the panel layout', function (): void {
    $this->actingAs($this->user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('infinito-onboarding.js', escape: false)
        ->assertSee('infinito-onboarding.css', escape: false);
});

it('honours the preview flag for authorised users on a real page', function (): void {
    $this->user->update(['roles' => ['tour-author']]);
    $tour = Tour::factory()->create(['key' => 'seen-already']);
    TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

    $this->actingAs($this->user)
        ->get('/admin?onboarding-preview=1')
        ->assertOk()
        ->assertSee('data-tour-key="seen-already"', escape: false);
});

it('reports authorisation through the plugin', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(InfinitoOnboardingPlugin::get()->isAuthorized($this->user))->toBeFalse();

    $this->user->update(['roles' => ['tour-author']]);

    expect(InfinitoOnboardingPlugin::get()->isAuthorized($this->user))->toBeTrue()
        ->and(InfinitoOnboardingPlugin::get()->isAuthorized(null))->toBeFalse();
});
