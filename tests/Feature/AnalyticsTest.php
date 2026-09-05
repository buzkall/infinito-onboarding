<?php

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\EditTour;
use Arzcode\InfinitoOnboarding\Filament\Widgets\TourAnalyticsWidget;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Support\TourAnalytics;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('tracking', function (): void {
    it('records browser-reported view, step and target_missing events with validated meta', function (): void {
        $tour = Tour::factory()->create(['version' => '2']);
        $step = TourStep::factory()->for($tour)->create();
        $foreign = TourStep::factory()->create();

        $component = Livewire::test(TourOverlay::class, ['tourId' => $tour->id, 'tenantId' => 'acme']);
        $component->call('track', 'view', ['index' => 0]);
        $component->call('track', 'step', ['step_id' => $step->id, 'index' => 0, 'ignored' => 'x']);
        $component->call('track', 'step', ['step_id' => $foreign->id]);
        $component->call('track', 'target_missing', ['selector' => '#gone', 'step_title' => 'Gone', 'step_id' => 'abc']);
        $component->call('track', 'hacked', []);

        expect(TourEvent::count())->toBe(4);

        $events = TourEvent::query()->orderBy('id')->get();

        expect($events[0]->event)->toBe(TourEventType::View)
            ->and($events[0]->version)->toBe('2')
            ->and($events[0]->tenant_id)->toBe('acme')
            ->and($events[0]->user_id)->toBe((string) $this->user->id)
            ->and($events[1]->step_id)->toBe($step->id)
            ->and($events[1]->meta)->toBe(['index' => 0])
            ->and($events[2]->step_id)->toBeNull()
            ->and($events[3]->event)->toBe(TourEventType::TargetMissing)
            ->and($events[3]->meta)->toBe(['selector' => '#gone', 'step_title' => 'Gone'])
            ->and($events[3]->step_id)->toBeNull();
    });

    it('records completed and dismissed events from the mark actions', function (): void {
        $tour = Tour::factory()->create();

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markCompleted');
        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('markDismissed');

        expect(TourEvent::query()->ofType(TourEventType::Completed)->count())->toBe(1)
            ->and(TourEvent::query()->ofType(TourEventType::Dismissed)->count())->toBe(1);
    });

    it('never records events in preview mode or when analytics are disabled', function (): void {
        $tour = Tour::factory()->create();

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id, 'preview' => true])->call('track', 'view')->call('markCompleted');

        expect(TourEvent::count())->toBe(0);

        config()->set('infinito-onboarding.analytics.enabled', false);

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])->call('track', 'view')->call('markCompleted');

        expect(TourEvent::count())->toBe(0);
    });

    it('wires the onEvent callback and a changelog view into the overlay markup', function (): void {
        $tour = Tour::factory()->create();
        TourStep::factory()->for($tour)->create();

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])
            ->assertSeeHtml('onEvent: (name, detail) => $wire.track(name, detail)');

        $changelog = Tour::factory()->changelog()->create();

        Livewire::test(TourOverlay::class, ['tourId' => $changelog->id])
            ->assertSeeHtml("\$wire.track('view')");
    });
});

describe('summary', function (): void {
    it('aggregates views, completions, per-step drop-off and missing targets for the current version', function (): void {
        $tour = Tour::factory()->create(['version' => '2']);
        [$a, $b, $c] = [
            TourStep::factory()->for($tour)->order(1)->create(['title' => 'A']),
            TourStep::factory()->for($tour)->order(2)->create(['title' => 'B']),
            TourStep::factory()->for($tour)->order(3)->create(['title' => 'C']),
        ];

        foreach ([1, 2, 3, 4] as $userId) {
            TourEvent::factory()->for($tour)->type(TourEventType::View)->create(['user_id' => $userId, 'version' => '2']);
            TourEvent::factory()->for($tour)->type(TourEventType::Step)->create(['user_id' => $userId, 'version' => '2', 'step_id' => $a->id]);
        }
        TourEvent::factory()->for($tour)->type(TourEventType::View)->create(['user_id' => 1, 'version' => '2']);

        foreach ([1, 2, 3] as $userId) {
            TourEvent::factory()->for($tour)->type(TourEventType::Step)->create(['user_id' => $userId, 'version' => '2', 'step_id' => $b->id]);
        }
        TourEvent::factory()->for($tour)->type(TourEventType::Step)->create(['user_id' => 1, 'version' => '2', 'step_id' => $c->id]);
        TourEvent::factory()->for($tour)->type(TourEventType::Completed)->create(['user_id' => 1, 'version' => '2']);
        TourEvent::factory()->for($tour)->type(TourEventType::Dismissed)->count(2)->create(['version' => '2']);
        TourEvent::factory()->for($tour)->type(TourEventType::TargetMissing)->count(2)->create(['version' => '2', 'meta' => ['selector' => '#gone', 'step_title' => 'C']]);
        TourEvent::factory()->for($tour)->type(TourEventType::TargetMissing)->create(['version' => '2', 'meta' => ['selector' => '.other']]);

        // Old version noise
        TourEvent::factory()->for($tour)->type(TourEventType::View)->count(10)->create(['version' => '1']);

        $summary = app(TourAnalytics::class)->summary($tour);

        expect($summary)->toMatchArray([
            'version' => '2',
            'views' => 5,
            'unique_viewers' => 4,
            'completed' => 1,
            'dismissed' => 2,
            'completion_rate' => 20.0,
        ])
            ->and($summary['steps'][0])->toMatchArray(['title' => 'A', 'reached' => 4, 'reached_rate' => 80.0, 'drop_off' => 1])
            ->and($summary['steps'][1])->toMatchArray(['title' => 'B', 'reached' => 3, 'reached_rate' => 60.0, 'drop_off' => 1])
            ->and($summary['steps'][2])->toMatchArray(['title' => 'C', 'reached' => 1, 'reached_rate' => 20.0, 'drop_off' => 2])
            ->and($summary['missing_targets'][0])->toMatchArray(['selector' => '#gone', 'step_title' => 'C', 'count' => 2])
            ->and($summary['missing_targets'][1])->toMatchArray(['selector' => '.other', 'count' => 1]);

        expect(app(TourAnalytics::class)->summary($tour, allVersions: true)['views'])->toBe(15);
    });

    it('returns null rates when there are no views', function (): void {
        $tour = Tour::factory()->create();

        $summary = app(TourAnalytics::class)->summary($tour);

        expect($summary['views'])->toBe(0)
            ->and($summary['completion_rate'])->toBeNull()
            ->and($summary['steps'])->toBe([])
            ->and($summary['missing_targets'])->toBe([]);
    });
});

describe('widget', function (): void {
    beforeEach(function (): void {
        $this->author = User::factory()->create(['roles' => ['tour-author']]);
        $this->actingAs($this->author);
    });

    it('renders the summary on the edit page', function (): void {
        $tour = Tour::factory()->create(['version' => '3']);
        $step = TourStep::factory()->for($tour)->create(['title' => 'Only step']);
        TourEvent::factory()->for($tour)->type(TourEventType::View)->count(3)->create(['version' => '3']);
        TourEvent::factory()->for($tour)->type(TourEventType::Step)->create(['version' => '3', 'step_id' => $step->id]);
        TourEvent::factory()->for($tour)->type(TourEventType::TargetMissing)->create(['version' => '3', 'meta' => ['selector' => '#nope']]);

        $this->get(EditTour::getUrl(['record' => $tour]))
            ->assertOk()
            ->assertSee('data-tour-analytics', escape: false)
            ->assertSee('Only step')
            ->assertSee('#nope');

        Livewire::test(TourAnalyticsWidget::class, ['record' => $tour])
            ->assertSeeHtml('data-stat="views"')
            ->assertSee('Current version 3')
            ->call('toggleVersions')
            ->assertSee('All versions');
    });

    it('is hidden when analytics are disabled', function (): void {
        config()->set('infinito-onboarding.analytics.enabled', false);

        expect(TourAnalyticsWidget::canView())->toBeFalse();
    });
});

describe('pruning', function (): void {
    it('deletes events older than the retention period', function (): void {
        $tour = Tour::factory()->create();
        TourEvent::factory()->for($tour)->create(['created_at' => now()->subDays(100)]);
        TourEvent::factory()->for($tour)->create(['created_at' => now()->subDays(10)]);

        $this->artisan('onboarding:prune-events')->assertSuccessful()->expectsOutputToContain('Deleted 1 event(s)');

        expect(TourEvent::count())->toBe(1);

        $this->artisan('onboarding:prune-events', ['--days' => 5])->assertSuccessful();

        expect(TourEvent::count())->toBe(0);
    });

    it('cascades when the tour is deleted', function (): void {
        $tour = Tour::factory()->create();
        TourEvent::factory()->for($tour)->count(2)->create();

        $tour->delete();

        expect(TourEvent::count())->toBe(0);
    });
});
