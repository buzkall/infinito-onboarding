<?php

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Carbon\CarbonImmutable;

it('uses the configured table names', function (): void {
    config()->set('infinito-onboarding.table_names.tours', 'custom_tours');
    config()->set('infinito-onboarding.table_names.tour_steps', 'custom_steps');
    config()->set('infinito-onboarding.table_names.tour_completions', 'custom_completions');

    expect((new Tour)->getTable())->toBe('custom_tours')
        ->and((new TourStep)->getTable())->toBe('custom_steps')
        ->and((new TourCompletion)->getTable())->toBe('custom_completions');
});

it('casts attributes to enums, immutable dates and arrays', function (): void {
    $tour = Tour::factory()->create([
        'mode' => 'changelog',
        'audience' => ['roles' => ['admin']],
        'published_at' => '2026-01-01 10:00:00',
    ]);

    $tour->refresh();

    expect($tour->mode)->toBe(TourMode::Changelog)
        ->and($tour->audience)->toBe(['roles' => ['admin']])
        ->and($tour->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($tour->is_active)->toBeTrue();
});

it('orders steps by their order column', function (): void {
    $tour = Tour::factory()->create();

    TourStep::factory()->for($tour)->order(3)->create(['title' => 'Third']);
    TourStep::factory()->for($tour)->order(1)->create(['title' => 'First']);
    TourStep::factory()->for($tour)->order(2)->create(['title' => 'Second']);

    expect($tour->steps->pluck('title')->all())->toBe(['First', 'Second', 'Third']);
});

it('deletes steps and completions when the tour is deleted', function (): void {
    $tour = Tour::factory()->create();
    TourStep::factory()->for($tour)->count(2)->create();
    TourCompletion::factory()->for($tour)->create();

    $tour->delete();

    expect(TourStep::count())->toBe(0)
        ->and(TourCompletion::count())->toBe(0);
});

describe('scopes', function (): void {
    it('active() excludes inactive tours', function (): void {
        Tour::factory()->create(['key' => 'on']);
        Tour::factory()->inactive()->create(['key' => 'off']);

        expect(Tour::active()->pluck('key')->all())->toBe(['on']);
    });

    it('published() excludes unpublished and future-published tours', function (): void {
        Tour::factory()->create(['key' => 'live', 'published_at' => now()->subMinute()]);
        Tour::factory()->unpublished()->create(['key' => 'draft']);
        Tour::factory()->create(['key' => 'scheduled', 'published_at' => now()->addDay()]);

        expect(Tour::published()->pluck('key')->all())->toBe(['live']);
    });

    it('withinWindow() honours open, closed and expired windows', function (): void {
        Tour::factory()->create(['key' => 'open']);
        Tour::factory()->create(['key' => 'running', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        Tour::factory()->create(['key' => 'not-yet', 'starts_at' => now()->addDay()]);
        Tour::factory()->create(['key' => 'expired', 'ends_at' => now()->subDay()]);

        expect(Tour::withinWindow()->pluck('key')->sort()->values()->all())->toBe(['open', 'running']);
    });

    it('forRoute() keeps null, exact and wildcard candidates only', function (): void {
        Tour::factory()->create(['key' => 'everywhere']);
        Tour::factory()->forRoute('admin/orders')->create(['key' => 'exact']);
        Tour::factory()->forRoute('admin/orders*')->create(['key' => 'wildcard']);
        Tour::factory()->forRoute('admin/customers')->create(['key' => 'other']);

        expect(Tour::forRoute('admin/orders')->pluck('key')->sort()->values()->all())
            ->toBe(['everywhere', 'exact', 'wildcard']);
    });

    it('forTenant() returns global tours plus the tenant\'s own', function (): void {
        Tour::factory()->create(['key' => 'global']);
        Tour::factory()->forTenant('acme')->create(['key' => 'acme']);
        Tour::factory()->forTenant('other')->create(['key' => 'other']);

        expect(Tour::forTenant('acme')->pluck('key')->sort()->values()->all())->toBe(['acme', 'global'])
            ->and(Tour::forTenant(null)->pluck('key')->all())->toBe(['global']);
    });

    it('ordered() sorts by sort then id', function (): void {
        Tour::factory()->create(['key' => 'b', 'sort' => 2]);
        Tour::factory()->create(['key' => 'a', 'sort' => 1]);
        Tour::factory()->create(['key' => 'c', 'sort' => 2]);

        expect(Tour::ordered()->pluck('key')->all())->toBe(['a', 'b', 'c']);
    });
});

describe('matchesRoute()', function (): void {
    it('matches everything when the pattern is empty', function (): void {
        expect(Tour::factory()->make(['route_pattern' => null])->matchesRoute('anything/here'))->toBeTrue();
    });

    it('supports wildcards and leading slashes', function (): void {
        $tour = Tour::factory()->make(['route_pattern' => 'admin/orders*']);

        expect($tour->matchesRoute('admin/orders'))->toBeTrue()
            ->and($tour->matchesRoute('/admin/orders/12/edit'))->toBeTrue()
            ->and($tour->matchesRoute('admin/customers'))->toBeFalse();
    });

    it('supports comma-separated pattern lists', function (): void {
        $tour = Tour::factory()->make(['route_pattern' => 'admin/orders, admin/customers/*']);

        expect($tour->matchesRoute('admin/orders'))->toBeTrue()
            ->and($tour->matchesRoute('admin/customers/4'))->toBeTrue()
            ->and($tour->matchesRoute('admin/products'))->toBeFalse();
    });
});

it('knows whether a user has seen the current version', function (): void {
    $tour = Tour::factory()->version('2')->create();

    TourCompletion::factory()->for($tour)->create(['user_id' => 7, 'seen_version' => '1']);

    expect($tour->isSeenBy(7))->toBeFalse();

    TourCompletion::factory()->for($tour)->create(['user_id' => 7, 'seen_version' => '2']);

    expect($tour->isSeenBy(7))->toBeTrue()
        ->and($tour->isSeenBy(7, 'acme'))->toBeFalse();
});
