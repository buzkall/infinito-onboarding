<?php

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\CreateTour;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Support\Segments;
use Arzcode\InfinitoOnboarding\Support\TourResolver;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->resolver = app(TourResolver::class);
    $this->user = User::factory()->create(['roles' => ['editor']]);
});

it('lists segments from the config and from code', function (): void {
    config()->set('infinito-onboarding.segments', ['admins' => ['roles' => ['admin']]]);
    config()->set('infinito-onboarding.segment_labels', ['admins' => 'Administrators']);

    app(Segments::class)->register('beta', fn (User $user): bool => str_ends_with($user->email, '@beta.test'));

    expect(app(Segments::class)->names())->toBe(['admins', 'beta'])
        ->and(app(Segments::class)->options())->toBe(['admins' => 'Administrators', 'beta' => 'Beta'])
        ->and(app(Segments::class)->has('nope'))->toBeFalse();
});

it('matches criteria segments and closure segments', function (): void {
    config()->set('infinito-onboarding.segments', ['editors' => ['roles' => ['editor']]]);
    app(Segments::class)->register('beta', fn (User $user): bool => $user->id === 999);

    $segments = app(Segments::class);

    expect($segments->matches('editors', $this->user))->toBeTrue()
        ->and($segments->matches('beta', $this->user))->toBeFalse()
        ->and($segments->matches('unknown', $this->user))->toBeFalse();

    $this->user->id = 999;

    expect($segments->matches('beta', $this->user))->toBeTrue();
});

it('gates tours by any of the listed segments', function (): void {
    config()->set('infinito-onboarding.segments', [
        'admins' => ['roles' => ['admin']],
        'editors' => ['roles' => ['editor']],
    ]);

    Tour::factory()->audience(['segments' => ['admins']])->create(['key' => 'admins-only']);
    Tour::factory()->audience(['segments' => ['admins', 'editors']])->create(['key' => 'either']);
    Tour::factory()->audience(['segments' => ['unknown']])->create(['key' => 'unknown']);
    Tour::factory()->audience(['segments' => ['editors'], 'permissions' => ['secret']])->create(['key' => 'segment-and-permission']);

    expect($this->resolver->candidatesFor($this->user, 'admin')->pluck('key')->all())->toBe(['either']);
});

it('registers segments through the plugin and the builder', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    InfinitoOnboardingPlugin::get()->segment('editors', ['roles' => ['editor']])->register(Filament::getPanel('admin'));

    $tour = Tour::define('seg')->segments('editors', 'vip')->save();

    expect($tour->audience)->toBe(['segments' => ['editors', 'vip']])
        ->and($this->resolver->passesAudience($tour, $this->user))->toBeTrue()
        ->and($this->resolver->passesAudience($tour, User::factory()->create()))->toBeFalse();
});

it('offers a segments select in the resource only when segments exist', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['roles' => ['tour-author']]));

    Livewire::test(CreateTour::class)->assertDontSee('Segments');

    config()->set('infinito-onboarding.segments', ['admins' => ['roles' => ['admin']]]);

    Livewire::test(CreateTour::class)
        ->assertSee('Segments')
        ->fillForm(['name' => 'Seg', 'key' => 'seg', 'audience' => ['segments' => ['admins']]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Tour::query()->where('key', 'seg')->first()->audience)->toBe(['segments' => ['admins']]);
});
