<?php

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Support\TourResolver;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->resolver = app(TourResolver::class);
    $this->user = User::factory()->create();
});

it('resolves an eligible tour', function (): void {
    $tour = Tour::factory()->forRoute('admin/orders*')->create();

    expect($this->resolver->resolveFor($this->user, 'admin/orders/1/edit'))
        ->toBeInstanceOf(Tour::class)
        ->id->toBe($tour->id);
});

it('returns null when nothing matches', function (): void {
    expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
});

describe('exclusions', function (): void {
    it('excludes inactive tours', function (): void {
        Tour::factory()->inactive()->create();

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes unpublished tours', function (): void {
        Tour::factory()->unpublished()->create();

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes tours scheduled to publish in the future', function (): void {
        Tour::factory()->create(['published_at' => now()->addHour()]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes tours whose window has not started', function (): void {
        Tour::factory()->create(['starts_at' => now()->addDay()]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes tours whose window has ended', function (): void {
        Tour::factory()->create(['ends_at' => now()->subDay()]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes tours whose route pattern does not match', function (): void {
        Tour::factory()->forRoute('admin/orders*')->create();

        expect($this->resolver->resolveFor($this->user, 'admin/customers'))->toBeNull();
    });

    it('excludes tours for another tenant', function (): void {
        Tour::factory()->forTenant('acme')->create();

        expect($this->resolver->resolveFor($this->user, 'admin', 'globex'))->toBeNull()
            ->and($this->resolver->resolveFor($this->user, 'admin', null))->toBeNull();
    });

    it('includes global tours and the current tenant\'s tours', function (): void {
        Tour::factory()->forTenant('acme')->create(['key' => 'acme', 'sort' => 1]);
        Tour::factory()->create(['key' => 'global', 'sort' => 2]);

        expect($this->resolver->candidatesFor($this->user, 'admin', 'acme')->pluck('key')->all())
            ->toBe(['acme', 'global']);
    });

    it('excludes tours the user already completed for the current version', function (): void {
        $tour = Tour::factory()->version('3')->create();
        TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '3']);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('excludes tours the user dismissed for the current version', function (): void {
        $tour = Tour::factory()->create();
        TourCompletion::factory()->dismissed()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('scopes seen-state to the tenant', function (): void {
        $tour = Tour::factory()->create();
        TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'tenant_id' => 'acme', 'seen_version' => '1']);

        expect($this->resolver->resolveFor($this->user, 'admin', 'acme'))->toBeNull()
            ->and($this->resolver->resolveFor($this->user, 'admin', 'globex'))->not->toBeNull();
    });
});

it('re-shows a tour to a user after its version is bumped', function (): void {
    $tour = Tour::factory()->version('1.0.0')->create();
    TourCompletion::factory()->for($tour)->create(['user_id' => $this->user->id, 'seen_version' => '1.0.0']);

    expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();

    $tour->update(['version' => '1.1.0']);

    expect($this->resolver->resolveFor($this->user, 'admin')?->id)->toBe($tour->id);
});

it('returns the first eligible tour by sort', function (): void {
    Tour::factory()->create(['key' => 'second', 'sort' => 20]);
    Tour::factory()->create(['key' => 'first', 'sort' => 10]);
    Tour::factory()->inactive()->create(['key' => 'zero', 'sort' => 0]);

    expect($this->resolver->resolveFor($this->user, 'admin')?->key)->toBe('first');
});

it('can restrict resolution to a mode', function (): void {
    Tour::factory()->create(['key' => 'tour', 'sort' => 1]);
    Tour::factory()->changelog()->create(['key' => 'log', 'sort' => 2]);

    expect($this->resolver->resolveFor($this->user, 'admin', mode: TourMode::Changelog)?->key)->toBe('log')
        ->and($this->resolver->resolveFor($this->user, 'admin', mode: TourMode::Tour)?->key)->toBe('tour');
});

it('lists unseen tours regardless of route', function (): void {
    Tour::factory()->forRoute('admin/orders')->changelog()->create(['key' => 'orders-log']);
    $seen = Tour::factory()->changelog()->create(['key' => 'seen-log']);
    TourCompletion::factory()->for($seen)->create(['user_id' => $this->user->id, 'seen_version' => '1']);

    expect($this->resolver->unseenFor($this->user, mode: TourMode::Changelog)->pluck('key')->all())
        ->toBe(['orders-log']);
});

describe('audience gate', function (): void {
    it('lets everyone through when the audience is empty', function (): void {
        Tour::factory()->audience([])->create();

        expect($this->resolver->resolveFor($this->user, 'admin'))->not->toBeNull();
    });

    it('excludes users without any of the required roles', function (): void {
        Tour::factory()->audience(['roles' => ['admin', 'editor']])->create();

        $this->user->update(['roles' => ['viewer']]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('includes users with one of the required roles from a roles attribute', function (): void {
        Tour::factory()->audience(['roles' => ['admin', 'editor']])->create();

        $this->user->update(['roles' => ['editor']]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->not->toBeNull();
    });

    it('uses hasAnyRole() when the user model provides it', function (): void {
        Tour::factory()->audience(['roles' => ['admin']])->create();

        $user = new class extends User
        {
            protected $table = 'users';

            public function hasAnyRole(array $roles): bool
            {
                return in_array('admin', $roles, true);
            }
        };
        $user->forceFill(['id' => 999, 'name' => 'x', 'email' => 'x@x.test', 'password' => 'x']);

        expect($this->resolver->resolveFor($user, 'admin'))->not->toBeNull();
    });

    it('excludes users failing every required permission', function (): void {
        Tour::factory()->audience(['permissions' => ['export orders']])->create();

        Gate::define('export orders', fn (User $user) => false);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('includes users passing a required permission through the Gate', function (): void {
        Tour::factory()->audience(['permissions' => ['export orders', 'delete orders']])->create();

        Gate::define('delete orders', fn (User $user) => true);

        expect($this->resolver->resolveFor($this->user, 'admin'))->not->toBeNull();
    });

    it('does not crash when no permission package is installed', function (): void {
        Tour::factory()->audience(['permissions' => ['undefined ability']])->create();

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('supports an explicit user list', function (): void {
        $other = User::factory()->create();
        Tour::factory()->audience(['users' => [$other->id]])->create();

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull()
            ->and($this->resolver->resolveFor($other, 'admin'))->not->toBeNull();
    });

    it('requires every present criterion to pass', function (): void {
        Tour::factory()->audience(['roles' => ['editor'], 'permissions' => ['export orders']])->create();
        $this->user->update(['roles' => ['editor']]);
        Gate::define('export orders', fn (User $user) => false);

        expect($this->resolver->resolveFor($this->user, 'admin'))->toBeNull();
    });

    it('accepts comma-separated strings as lists', function (): void {
        Tour::factory()->audience(['roles' => 'admin, editor'])->create();
        $this->user->update(['roles' => ['editor']]);

        expect($this->resolver->resolveFor($this->user, 'admin'))->not->toBeNull();
    });
});
