<?php

use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Livewire\ChangelogTrigger;
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
});

describe('auto-show', function (): void {
    it('renders a changelog tour as a modal with release-note entries instead of Driver.js', function (): void {
        $tour = Tour::factory()->changelog()->create(['key' => 'q3', 'name' => 'Q3 release', 'version' => '3.0']);
        TourStep::factory()->for($tour)->untargeted()->order(1)->create(['title' => 'Faster exports', 'body' => '<p>Exports are now async.</p>']);
        TourStep::factory()->for($tour)->untargeted()->order(2)->create(['title' => 'Dark mode']);

        $this->actingAs($this->user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-tour-changelog', escape: false)
            ->assertSee('open-modal', escape: false)
            ->assertSee('Faster exports')
            ->assertSee('Exports are now async.')
            ->assertSee('Dark mode')
            ->assertDontSee('infinitoOnboardingTour(', escape: false);
    });

    it('is shown once per user per version through the overlay', function (): void {
        $tour = Tour::factory()->changelog()->create(['version' => '1']);
        $this->actingAs($this->user);

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])
            ->assertSeeHtml('data-tour-changelog')
            ->assertSeeHtml('data-changelog-complete')
            ->call('markCompleted');

        expect(TourCompletion::count())->toBe(1);

        Livewire::test(TourOverlay::class, ['path' => 'admin'])->assertSet('tourId', null);

        $tour->update(['version' => '2']);

        Livewire::test(TourOverlay::class, ['path' => 'admin'])->assertSet('tourId', $tour->id);
    });

    it('records a dismissal when the modal is closed without confirming', function (): void {
        $tour = Tour::factory()->changelog()->create();
        $this->actingAs($this->user);

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markDismissed');

        expect(TourCompletion::first()->dismissed_at)->not->toBeNull();
    });
});

describe('topbar trigger', function (): void {
    it('is not rendered unless ->topbarTrigger() is enabled', function (): void {
        Tour::factory()->changelog()->forRoute('nowhere')->create();

        $this->actingAs($this->user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-changelog-trigger', escape: false);
    });

    it('renders in the topbar with a dot while an unseen changelog exists', function (): void {
        $tour = Tour::factory()->changelog()->forRoute('nowhere')->create(['name' => 'Spring release']);
        TourStep::factory()->for($tour)->untargeted()->create(['title' => 'New filters']);
        InfinitoOnboardingPlugin::get()->topbarTrigger();

        try {
            $this->actingAs($this->user)
                ->get('/admin')
                ->assertOk()
                ->assertSee('data-changelog-trigger', escape: false)
                ->assertSee('data-unseen', escape: false)
                ->assertSee('New filters');
        } finally {
            InfinitoOnboardingPlugin::get()->topbarTrigger(false);
        }
    });

    it('lists the latest changelogs regardless of route and seen-state', function (): void {
        $old = Tour::factory()->changelog()->forRoute('admin/orders')->create(['name' => 'Old', 'published_at' => now()->subMonth()]);
        $new = Tour::factory()->changelog()->forRoute('admin/customers')->create(['name' => 'New', 'published_at' => now()->subDay()]);
        Tour::factory()->create(['name' => 'Guided tour, not a changelog']);
        Tour::factory()->changelog()->unpublished()->create(['name' => 'Draft']);
        TourCompletion::factory()->for($old)->create(['user_id' => $this->user->id, 'seen_version' => '1']);
        $this->actingAs($this->user);

        $component = Livewire::test(ChangelogTrigger::class);

        expect($component->instance()->getChangelogs()->pluck('name')->all())->toBe(['New', 'Old'])
            ->and($component->instance()->getUnseenChangelogs()->pluck('name')->all())->toBe(['New'])
            ->and($component->instance()->hasUnseen())->toBeTrue();

        $component->assertSeeHtml('data-unseen')->assertSee('New')->assertSee('Old')->assertDontSee('Draft');
    });

    it('marks every unseen changelog as seen when confirmed and drops the dot', function (): void {
        $a = Tour::factory()->changelog()->create(['version' => '1']);
        $b = Tour::factory()->changelog()->create(['version' => '4']);
        $this->actingAs($this->user);

        $component = Livewire::test(ChangelogTrigger::class)->call('markCompleted');

        expect(TourCompletion::count())->toBe(2)
            ->and(TourCompletion::query()->where('tour_id', $b->id)->value('seen_version'))->toBe('4')
            ->and($component->instance()->hasUnseen())->toBeFalse();

        $component->assertDontSeeHtml('data-unseen')->assertSeeHtml('data-changelog-trigger');

        $component->call('markCompleted');

        expect(TourCompletion::count())->toBe(2);
    });

    it('renders nothing when there are no changelogs at all', function (): void {
        Tour::factory()->create();
        $this->actingAs($this->user);

        Livewire::test(ChangelogTrigger::class)
            ->assertSeeHtml('data-changelog-trigger')
            ->assertDontSeeHtml('io-changelog-trigger-btn');
    });

    it('respects the audience gate', function (): void {
        Tour::factory()->changelog()->audience(['roles' => ['admin']])->create();
        $this->actingAs($this->user);

        expect(Livewire::test(ChangelogTrigger::class)->instance()->getChangelogs())->toHaveCount(0);
    });
});
