<?php

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\Team;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->panel = Panel::make()->id('teams')->path('teams')->tenant(Team::class);
    Filament::setCurrentPanel($this->panel);
    Filament::setTenant((new Team)->forceFill(['id' => 1]), isQuiet: true);
});

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
    Tour::clearBootedModels();
});

it('keeps tour queries working when the resource is registered in a tenant panel', function (): void {
    TourResource::registerTenancyModelGlobalScope($this->panel);
    Tour::factory()->create();

    expect(Tour::query()->count())->toBe(1);
});

it('only lists the current tenant\'s tours in the resource', function (): void {
    $own = Tour::factory()->forTenant('1')->create(['key' => 'own']);
    Tour::factory()->forTenant('2')->create(['key' => 'other']);
    Tour::factory()->create(['key' => 'global']);

    expect(TourResource::getEloquentQuery()->pluck('id')->all())->toBe([$own->id]);
});

it('stores new and edited tours under the current tenant', function (): void {
    expect(TourResource::mutateFormData(['tenant_id' => '2'])['tenant_id'])->toBe('1');
});

it('never opens another tenant\'s tour in record mode', function (): void {
    Tour::factory()->forTenant('2')->create(['key' => 'other']);

    expect(TourRecorder::resolveTourForRequest(Request::create('/teams/1', 'GET', ['onboarding-record' => 'other'])))->toBeNull();
});

it('creates record-mode drafts under the current tenant', function (): void {
    $tour = TourRecorder::resolveTourForRequest(Request::create('/teams/1', 'GET', ['onboarding-record' => 'fresh']));

    expect($tour->tenant_id)->toBe('1');
});
