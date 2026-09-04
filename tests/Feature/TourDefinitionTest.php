<?php

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;

it('creates a tour with steps from a fluent definition', function (): void {
    $tour = Tour::define('q3-2026')
        ->route('admin/orders*')
        ->version('2.4.0')
        ->description('Quarterly release')
        ->step('export-btn', 'Export orders', 'You can now export…', Placement::Bottom)
        ->cssStep('#filters', 'Filters', null, 'left')
        ->note('Enjoy', '<p>That is all.</p>')
        ->roles(['admin', 'editor'])
        ->publish()
        ->save();

    expect($tour->key)->toBe('q3-2026')
        ->and($tour->name)->toBe('Q3 2026')
        ->and($tour->route_pattern)->toBe('admin/orders*')
        ->and($tour->version)->toBe('2.4.0')
        ->and($tour->mode)->toBe(TourMode::Tour)
        ->and($tour->published_at)->not->toBeNull()
        ->and($tour->audience)->toBe(['roles' => ['admin', 'editor']])
        ->and($tour->steps)->toHaveCount(3)
        ->and($tour->steps[0]->target_type)->toBe(TargetType::DataTour)
        ->and($tour->steps[0]->target)->toBe('export-btn')
        ->and($tour->steps[0]->placement)->toBe(Placement::Bottom)
        ->and($tour->steps[0]->order)->toBe(1)
        ->and($tour->steps[1]->target_type)->toBe(TargetType::Css)
        ->and($tour->steps[1]->placement)->toBe(Placement::Left)
        ->and($tour->steps[2]->target_type)->toBe(TargetType::None)
        ->and($tour->steps[2]->target)->toBeNull()
        ->and($tour->steps[2]->order)->toBe(3);
});

it('is idempotent: saving the same definition twice changes nothing', function (): void {
    $define = fn () => Tour::define('welcome')->route('admin')->step('a', 'A')->step('b', 'B')->publish('2026-01-01 10:00:00');

    $first = $define()->save();
    $second = $define()->save();

    expect(Tour::count())->toBe(1)
        ->and(TourStep::count())->toBe(2)
        ->and($second->id)->toBe($first->id)
        ->and($second->published_at->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('updates the tour and replaces the steps when the definition changes, keeping completions', function (): void {
    $tour = Tour::define('welcome')->step('a', 'A')->version('1')->save();
    TourCompletion::factory()->for($tour)->create(['seen_version' => '1']);

    $updated = Tour::define('welcome')->name('Welcome!')->step('b', 'B')->step('c', 'C')->version('2')->save();

    expect($updated->id)->toBe($tour->id)
        ->and($updated->name)->toBe('Welcome!')
        ->and($updated->version)->toBe('2')
        ->and($updated->steps->pluck('target')->all())->toBe(['b', 'c'])
        ->and(TourCompletion::count())->toBe(1);
});

it('supports changelog mode, windows, tenants, users and permissions', function (): void {
    $tour = Tour::define('release-notes')
        ->changelog()
        ->between('2026-01-01', '2026-12-31')
        ->tenant(42)
        ->users([1, 2])
        ->permissions('view releases')
        ->sort(5)
        ->active(false)
        ->note('New thing')
        ->save();

    expect($tour->mode)->toBe(TourMode::Changelog)
        ->and($tour->starts_at->toDateString())->toBe('2026-01-01')
        ->and($tour->ends_at->toDateString())->toBe('2026-12-31')
        ->and($tour->tenant_id)->toBe('42')
        ->and($tour->audience)->toBe(['users' => ['1', '2'], 'permissions' => ['view releases']])
        ->and($tour->sort)->toBe(5)
        ->and($tour->is_active)->toBeFalse();
});

it('joins multiple route patterns', function (): void {
    expect(Tour::define('x')->route('admin/orders', 'admin/customers/*')->getAttributes()['route_pattern'])
        ->toBe('admin/orders,admin/customers/*');
});
