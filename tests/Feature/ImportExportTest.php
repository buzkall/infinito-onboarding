<?php

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Support\TourExporter;
use Arzcode\InfinitoOnboarding\Support\TourImporter;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/infinito-onboarding-tours-' . uniqid();
    config()->set('infinito-onboarding.export_path', $this->dir);
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

function makeRichTour(): Tour
{
    $tour = Tour::factory()->create([
        'key' => 'orders-q3',
        'name' => 'Orders Q3',
        'description' => 'Ünïcode & <html>',
        'route_pattern' => 'admin/orders*',
        'version' => '3.1.4',
        'published_at' => '2026-03-01 09:30:00',
        'starts_at' => '2026-03-01 00:00:00',
        'ends_at' => null,
        'audience' => ['roles' => ['admin'], 'permissions' => ['export orders']],
        'tenant_id' => 'acme',
        'sort' => 7,
        'is_active' => true,
    ]);

    TourStep::factory()->for($tour)->order(1)->create(['target' => 'export-btn', 'title' => 'Export', 'body' => '<p>Hi</p>', 'placement' => 'bottom', 'extra' => ['score' => 'green']]);
    TourStep::factory()->for($tour)->order(2)->css('#filters')->create(['title' => 'Filters', 'body' => null, 'placement' => 'auto']);
    TourStep::factory()->for($tour)->order(3)->untargeted()->create(['title' => 'Bye']);

    return $tour;
}

it('exports a tour to a stable JSON document', function (): void {
    $tour = makeRichTour();

    $data = app(TourExporter::class)->toArray($tour);

    expect($data)->toMatchArray([
        'format' => 1,
        'key' => 'orders-q3',
        'mode' => 'tour',
        'version' => '3.1.4',
        'audience' => ['roles' => ['admin'], 'permissions' => ['export orders']],
        'tenant_id' => 'acme',
        'sort' => 7,
    ])
        ->and($data['steps'])->toHaveCount(3)
        ->and($data['steps'][0])->toMatchArray(['target_type' => 'data_tour', 'target' => 'export-btn', 'placement' => 'bottom', 'extra' => ['score' => 'green']])
        ->and($data['steps'][2])->toMatchArray(['target_type' => 'none', 'target' => null])
        ->and($data)->not->toHaveKey('id');

    $json = app(TourExporter::class)->toJson($tour);

    expect($json)->toContain('"key": "orders-q3"')->toContain('Ünïcode');
});

it('round-trips export → import with full fidelity', function (): void {
    $original = makeRichTour();
    $exported = app(TourExporter::class)->toArray($original);

    $original->delete();
    expect(Tour::count())->toBe(0);

    $imported = app(TourImporter::class)->importJson(json_encode($exported));

    expect(app(TourExporter::class)->toArray($imported))->toEqual($exported)
        ->and($imported->steps->pluck('order')->all())->toBe([1, 2, 3])
        ->and($imported->published_at->toDateTimeString())->toBe('2026-03-01 09:30:00');
});

it('matches on key when importing and never duplicates', function (): void {
    $tour = makeRichTour();
    TourCompletion::factory()->for($tour)->create(['seen_version' => '3.1.4']);
    $exported = app(TourExporter::class)->toArray($tour);
    $exported['name'] = 'Renamed';
    $exported['steps'] = [$exported['steps'][0]];

    $imported = app(TourImporter::class)->importArray($exported);

    expect(Tour::count())->toBe(1)
        ->and($imported->id)->toBe($tour->id)
        ->and($imported->name)->toBe('Renamed')
        ->and($imported->steps)->toHaveCount(1)
        ->and(TourCompletion::count())->toBe(1);
});

it('rejects newer format versions and documents without a key', function (): void {
    expect(fn () => app(TourImporter::class)->importArray(['format' => 99, 'key' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(TourImporter::class)->importArray(['name' => 'no key']))->toThrow(InvalidArgumentException::class);
});

it('exports and imports through the artisan commands', function (): void {
    makeRichTour();
    Tour::factory()->create(['key' => 'second']);

    $this->artisan('onboarding:export')
        ->assertSuccessful()
        ->expectsOutputToContain('Exported 2 tour(s)');

    expect(File::exists($this->dir . '/orders-q3.json'))->toBeTrue()
        ->and(File::exists($this->dir . '/second.json'))->toBeTrue();

    Tour::query()->delete();

    $this->artisan('onboarding:import')
        ->assertSuccessful()
        ->expectsOutputToContain('Imported 2 tour(s)');

    expect(Tour::query()->pluck('key')->sort()->values()->all())->toBe(['orders-q3', 'second'])
        ->and(Tour::query()->where('key', 'orders-q3')->first()->steps)->toHaveCount(3);
});

it('exports a single tour by key and fails for unknown keys', function (): void {
    makeRichTour();

    $this->artisan('onboarding:export', ['tour' => 'orders-q3', '--path' => $this->dir . '/custom'])->assertSuccessful();

    expect(File::exists($this->dir . '/custom/orders-q3.json'))->toBeTrue();

    $this->artisan('onboarding:export', ['tour' => 'nope'])->assertFailed();
});

it('deletes tours missing from the directory with --fresh', function (): void {
    makeRichTour();
    $this->artisan('onboarding:export')->assertSuccessful();
    Tour::factory()->create(['key' => 'only-in-db']);

    $this->artisan('onboarding:import', ['--fresh' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('deleted 1');

    expect(Tour::query()->pluck('key')->all())->toBe(['orders-q3']);
});

it('fails cleanly when the import directory does not exist', function (): void {
    $this->artisan('onboarding:import', ['--path' => $this->dir . '/missing'])->assertFailed();
});
