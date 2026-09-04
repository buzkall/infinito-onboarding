<?php

use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->author = User::factory()->create(['roles' => ['tour-author']]);
    $this->viewer = User::factory()->create();
});

describe('render hook', function (): void {
    it('renders the recorder instead of the overlay for authorised users', function (): void {
        $tour = Tour::factory()->create(['key' => 'orders']);
        TourStep::factory()->for($tour)->create();

        $this->actingAs($this->author)
            ->get('/admin?onboarding-record=orders')
            ->assertOk()
            ->assertSee('data-tour-recorder', escape: false)
            ->assertSee('infinitoOnboardingRecorder(', escape: false)
            ->assertSee('recorder.js', escape: false)
            ->assertDontSee('data-tour-overlay', escape: false);
    });

    it('never activates for unauthorised users', function (): void {
        Tour::factory()->create(['key' => 'orders']);

        $this->actingAs($this->viewer)
            ->get('/admin?onboarding-record=orders')
            ->assertOk()
            ->assertDontSee('data-tour-recorder', escape: false)
            ->assertDontSee('recorder.js', escape: false)
            ->assertSee('data-tour-overlay', escape: false);
    });

    it('creates an unpublished draft scoped to the current path when the key is new', function (): void {
        $this->actingAs($this->author)
            ->get('/admin?onboarding-record=brand-new-tour')
            ->assertOk()
            ->assertSee('data-tour-key="brand-new-tour"', escape: false);

        $tour = Tour::query()->where('key', 'brand-new-tour')->first();

        expect($tour)->not->toBeNull()
            ->and($tour->name)->toBe('Brand New Tour')
            ->and($tour->route_pattern)->toBe('admin')
            ->and($tour->published_at)->toBeNull();
    });

    it('rejects keys with unsafe characters', function (): void {
        $request = Request::create('/admin', 'GET', ['onboarding-record' => 'nope!']);

        expect(TourRecorder::resolveTourForRequest($request))->toBeNull()
            ->and(Tour::count())->toBe(0);
    });

    it('builds an exit url without the record flag', function (): void {
        $request = Request::create('/admin/orders?onboarding-record=x&page=2', 'GET');

        expect(TourRecorder::exitUrl($request))->toBe('http://localhost/admin/orders?page=2');
    });
});

describe('Livewire component', function (): void {
    it('refuses to mount for unauthorised users', function (): void {
        $tour = Tour::factory()->create();
        $this->actingAs($this->viewer);

        Livewire::test(TourRecorder::class, ['tourId' => $tour->id])->assertForbidden();
    });

    it('refuses to save for unauthorised users even with a mounted component', function (): void {
        $tour = Tour::factory()->create();
        $this->actingAs($this->author);
        $component = Livewire::test(TourRecorder::class, ['tourId' => $tour->id]);

        $this->actingAs($this->viewer);

        $component->call('saveSteps', [['title' => 'x', 'target_type' => 'none']])->assertForbidden();

        expect($tour->steps()->count())->toBe(0);
    });

    it('saves captured steps in order and returns their payloads', function (): void {
        $tour = Tour::factory()->create();
        $this->actingAs($this->author);

        $component = Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->call('saveSteps', [
                ['title' => 'Export', 'body' => '<p>Click</p>', 'placement' => 'bottom', 'target_type' => 'data_tour', 'target' => 'export-btn', 'extra' => ['strategy' => 'data-tour', 'score' => 'green']],
                ['title' => 'Filters', 'body' => '', 'placement' => 'auto', 'target_type' => 'css', 'target' => '#filters'],
                ['title' => 'Done', 'target_type' => 'none', 'target' => 'ignored'],
            ]);

        $steps = $tour->steps()->get();

        expect($steps)->toHaveCount(3)
            ->and($steps->pluck('title')->all())->toBe(['Export', 'Filters', 'Done'])
            ->and($steps->pluck('order')->all())->toBe([1, 2, 3])
            ->and($steps[0]->target_type)->toBe(TargetType::DataTour)
            ->and($steps[0]->extra)->toBe(['strategy' => 'data-tour', 'score' => 'green'])
            ->and($steps[2]->target)->toBeNull();

        $payload = $component->instance()->saveSteps([
            ['id' => $steps[0]->id, 'title' => 'Export', 'target_type' => 'data_tour', 'target' => 'export-btn'],
        ]);

        expect($payload)->toHaveCount(1)
            ->and($payload[0]['selector'])->toBe('[data-tour="export-btn"]')
            ->and($payload[0]['score'])->toBe('green');
    });

    it('keeps existing step ids, reorders and deletes removed steps', function (): void {
        $tour = Tour::factory()->create();
        $a = TourStep::factory()->for($tour)->order(1)->create(['title' => 'A']);
        $b = TourStep::factory()->for($tour)->order(2)->create(['title' => 'B']);
        $c = TourStep::factory()->for($tour)->order(3)->create(['title' => 'C']);
        $this->actingAs($this->author);

        Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->call('saveSteps', [
                ['id' => $c->id, 'title' => 'C', 'target_type' => 'data_tour', 'target' => 'c'],
                ['id' => $a->id, 'title' => 'A renamed', 'target_type' => 'data_tour', 'target' => 'a'],
            ]);

        expect($tour->steps()->pluck('title')->all())->toBe(['C', 'A renamed'])
            ->and($tour->steps()->pluck('id')->all())->toBe([$c->id, $a->id])
            ->and(TourStep::query()->whereKey($b->id)->exists())->toBeFalse();
    });

    it('validates the step payload', function (): void {
        $tour = Tour::factory()->create();
        $this->actingAs($this->author);

        Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->call('saveSteps', [['title' => '', 'target_type' => 'css', 'target' => '#x']])
            ->assertHasErrors(['steps.0.title']);

        Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->call('saveSteps', [['title' => 'x', 'target_type' => 'css', 'target' => null]])
            ->assertHasErrors(['steps.0.target']);

        expect($tour->steps()->count())->toBe(0);
    });

    it('renders the existing steps and labels into the Alpine payload', function (): void {
        $tour = Tour::factory()->create(['key' => 'orders']);
        TourStep::factory()->for($tour)->create(['title' => 'Existing step']);
        $this->actingAs($this->author);

        Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->assertSee('Existing step')
            ->assertSeeHtml('data-tour-key="orders"')
            ->assertSee('Pick element');
    });
});
