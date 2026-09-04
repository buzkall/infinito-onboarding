<?php

use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\CreateTour;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\EditTour;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\ListTours;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers\StepsRelationManager;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Support\PanelRoutes;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->author = User::factory()->create(['roles' => ['tour-author']]);
    $this->viewer = User::factory()->create();
});

it('registers the resource only when ->resource() is enabled', function (): void {
    expect(Filament::getPanel('admin')->getResources())->toContain(TourResource::class);

    $panel = Panel::make()->id('bare')->path('bare')->plugin(InfinitoOnboardingPlugin::make());

    expect($panel->getResources())->not->toContain(TourResource::class);
});

it('is gated behind the plugin authorize closure', function (): void {
    $this->actingAs($this->viewer);
    expect(TourResource::canAccess())->toBeFalse();
    $this->actingAs($this->viewer)->get(TourResource::getUrl('index'))->assertForbidden();

    $this->actingAs($this->author);
    expect(TourResource::canAccess())->toBeTrue();
    $this->actingAs($this->author)->get(TourResource::getUrl('index'))->assertOk();
});

it('lists tours with their counts', function (): void {
    $tour = Tour::factory()->create(['name' => 'Orders export', 'key' => 'orders-export']);
    TourStep::factory()->for($tour)->count(2)->create();
    TourCompletion::factory()->for($tour)->count(3)->create();

    $this->actingAs($this->author);

    Livewire::test(ListTours::class)
        ->assertCanSeeTableRecords([$tour])
        ->assertSee('Orders export')
        ->assertSee('orders-export')
        ->assertTableColumnStateSet('steps_count', 2, $tour)
        ->assertTableColumnStateSet('completions_count', 3, $tour);
});

it('creates a tour from the form and derives the key from the name', function (): void {
    $this->actingAs($this->author);

    Livewire::test(CreateTour::class)
        ->fillForm([
            'name' => 'What is new in Q3',
            'mode' => TourMode::Tour->value,
            'version' => '2.0',
            'route_pattern' => 'admin/orders*',
            'is_published' => true,
            'audience' => ['roles' => ['admin']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tour = Tour::query()->where('key', 'what-is-new-in-q3')->first();

    expect($tour)->not->toBeNull()
        ->and($tour->version)->toBe('2.0')
        ->and($tour->route_pattern)->toBe('admin/orders*')
        ->and($tour->published_at)->not->toBeNull()
        ->and($tour->audience)->toBe(['roles' => ['admin']]);
});

it('validates the key is unique and alpha-dash', function (): void {
    Tour::factory()->create(['key' => 'taken']);
    $this->actingAs($this->author);

    Livewire::test(CreateTour::class)
        ->fillForm(['name' => 'x', 'key' => 'taken'])
        ->call('create')
        ->assertHasFormErrors(['key' => 'unique']);

    Livewire::test(CreateTour::class)
        ->fillForm(['name' => 'x', 'key' => 'not valid!'])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('edits a tour and can unpublish it through the toggle', function (): void {
    $tour = Tour::factory()->create();
    $this->actingAs($this->author);

    Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
        ->assertFormSet(['is_published' => true])
        ->fillForm(['is_published' => false, 'version' => '3'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tour->refresh()->published_at)->toBeNull()
        ->and($tour->version)->toBe('3');
});

it('has a preview action pointing at the route with the preview flag', function (): void {
    $tour = Tour::factory()->forRoute('admin/orders*')->create(['key' => 'orders']);
    $this->actingAs($this->author);

    expect($tour->getPreviewUrl())->toBe(url('admin/orders') . '?onboarding-preview=orders');

    Livewire::test(ListTours::class)
        ->assertTableActionExists('preview')
        ->assertTableActionHasUrl('preview', $tour->getPreviewUrl(), $tour);
});

it('falls back to the panel root for tours without a route pattern', function (): void {
    $tour = Tour::factory()->create(['key' => 'everywhere']);

    expect($tour->getPreviewUrl())->toBe(url('admin') . '?onboarding-preview=everywhere');
});

it('resets seen-state through the table action', function (): void {
    $tour = Tour::factory()->create();
    TourCompletion::factory()->for($tour)->count(2)->create();
    TourCompletion::factory()->create();
    $this->actingAs($this->author);

    Livewire::test(ListTours::class)
        ->callTableAction('resetSeenState', $tour)
        ->assertNotified();

    expect($tour->completions()->count())->toBe(0)
        ->and(TourCompletion::count())->toBe(1);
});

describe('steps relation manager', function (): void {
    it('lists steps ordered and creates new steps at the end', function (): void {
        $tour = Tour::factory()->create();
        TourStep::factory()->for($tour)->order(1)->create(['title' => 'First']);
        $this->actingAs($this->author);

        $manager = Livewire::test(StepsRelationManager::class, [
            'ownerRecord' => $tour,
            'pageClass' => EditTour::class,
        ]);

        $manager
            ->assertCanSeeTableRecords($tour->steps)
            ->callTableAction('create', data: [
                'title' => 'Second',
                'target_type' => TargetType::DataTour->value,
                'target' => 'export-btn',
                'body' => '<p>Click here</p>',
                'placement' => 'bottom',
            ])
            ->assertHasNoTableActionErrors();

        $second = TourStep::query()->where('title', 'Second')->first();

        expect($second->order)->toBe(2)
            ->and($second->target_type)->toBe(TargetType::DataTour)
            ->and($tour->steps()->pluck('title')->all())->toBe(['First', 'Second']);
    });

    it('requires a target unless the step is untargeted', function (): void {
        $tour = Tour::factory()->create();
        $this->actingAs($this->author);

        $manager = Livewire::test(StepsRelationManager::class, [
            'ownerRecord' => $tour,
            'pageClass' => EditTour::class,
        ]);

        $manager
            ->callTableAction('create', data: ['title' => 'Missing target', 'target_type' => 'css', 'target' => null, 'placement' => 'auto'])
            ->assertHasTableActionErrors(['target' => 'required']);

        Livewire::test(StepsRelationManager::class, ['ownerRecord' => $tour, 'pageClass' => EditTour::class])
            ->callTableAction('create', data: ['title' => 'Centred', 'target_type' => 'none', 'placement' => 'auto'])
            ->assertHasNoTableActionErrors();

        expect(TourStep::query()->where('title', 'Centred')->first()->target_type)->toBe(TargetType::None);
    });

    it('supports drag-and-drop reordering on the order column', function (): void {
        $tour = Tour::factory()->create();
        $a = TourStep::factory()->for($tour)->order(1)->create();
        $b = TourStep::factory()->for($tour)->order(2)->create();
        $this->actingAs($this->author);

        Livewire::test(StepsRelationManager::class, ['ownerRecord' => $tour, 'pageClass' => EditTour::class])
            ->call('reorderTable', [$b->getKey(), $a->getKey()]);

        expect($tour->steps()->pluck('id')->all())->toBe([$b->id, $a->id]);
    });
});

it('suggests the panel routes as wildcard patterns', function (): void {
    $patterns = PanelRoutes::patternsFor(Filament::getPanel('admin'));

    expect($patterns)->toContain('admin')
        ->and($patterns)->toContain('admin/onboarding-tours')
        ->and($patterns)->toContain('admin/onboarding-tours/*/edit')
        ->and(PanelRoutes::patternsFor(null))->toBe([]);
});

it('strips empty audience criteria and handles the publish toggle when saving', function (): void {
    expect(TourResource::mutateFormData(['is_published' => false, 'published_at' => '2026-01-01', 'audience' => ['roles' => [], 'permissions' => ['']]]))
        ->toMatchArray(['published_at' => null, 'audience' => null])
        ->not->toHaveKey('is_published');

    $data = TourResource::mutateFormData(['is_published' => true, 'published_at' => null, 'audience' => ['roles' => ['admin', ''], 'permissions' => []]]);

    expect($data['published_at'])->not->toBeNull()
        ->and($data['audience'])->toBe(['roles' => ['admin']]);
});
