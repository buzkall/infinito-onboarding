<?php

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Illuminate\Database\UniqueConstraintViolationException;

it('enforces uniqueness on tour, user, tenant and version', function (): void {
    $tour = Tour::factory()->create();

    TourCompletion::factory()->for($tour)->create(['user_id' => 1, 'tenant_id' => null, 'seen_version' => '1']);

    expect(fn () => TourCompletion::factory()->for($tour)->create(['user_id' => 1, 'tenant_id' => null, 'seen_version' => '1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a new row for a different version, tenant or user', function (): void {
    $tour = Tour::factory()->create();

    TourCompletion::factory()->for($tour)->create(['user_id' => 1, 'seen_version' => '1']);
    TourCompletion::factory()->for($tour)->create(['user_id' => 1, 'seen_version' => '2']);
    TourCompletion::factory()->for($tour)->create(['user_id' => 1, 'seen_version' => '1', 'tenant_id' => 'acme']);
    TourCompletion::factory()->for($tour)->create(['user_id' => 2, 'seen_version' => '1']);

    expect(TourCompletion::count())->toBe(4);
});

it('normalises a null tenant to an empty string so the unique index works everywhere', function (): void {
    $completion = TourCompletion::factory()->create(['tenant_id' => null]);

    expect($completion->tenant_id)->toBe(TourCompletion::NO_TENANT)
        ->and(TourCompletion::normalizeTenantId(null))->toBe('')
        ->and(TourCompletion::normalizeTenantId('acme'))->toBe('acme');
});

it('stores the user id as a string so uuid and integer keys both work', function (): void {
    $completion = TourCompletion::factory()->create(['user_id' => 42]);

    expect($completion->user_id)->toBe('42');
});

it('scopes completions to a user and tenant', function (): void {
    TourCompletion::factory()->create(['user_id' => 1, 'tenant_id' => null]);
    TourCompletion::factory()->create(['user_id' => 1, 'tenant_id' => 'acme']);
    TourCompletion::factory()->create(['user_id' => 2, 'tenant_id' => null]);

    expect(TourCompletion::forUser(1)->count())->toBe(1)
        ->and(TourCompletion::forUser(1, 'acme')->count())->toBe(1)
        ->and(TourCompletion::forUser(3)->count())->toBe(0);
});

it('reports completed versus dismissed state', function (): void {
    expect(TourCompletion::factory()->make()->isCompleted())->toBeTrue()
        ->and(TourCompletion::factory()->dismissed()->make()->isDismissed())->toBeTrue()
        ->and(TourCompletion::factory()->dismissed()->make()->isCompleted())->toBeFalse();
});
