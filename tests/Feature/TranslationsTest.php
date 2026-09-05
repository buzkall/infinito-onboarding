<?php

use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\CreateTour;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\EditTour;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers\StepsRelationManager;
use Arzcode\InfinitoOnboarding\Livewire\TourOverlay;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Support\Locales;
use Arzcode\InfinitoOnboarding\Support\TourExporter;
use Arzcode\InfinitoOnboarding\Support\TourImporter;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('infinito-onboarding.locales', ['en', 'es', 'fr']);
    config()->set('app.fallback_locale', 'en');
    app()->setLocale('en');
});

describe('locales', function (): void {
    it('reads the configured locales with the first as default', function (): void {
        expect(Locales::all())->toBe(['en', 'es', 'fr'])
            ->and(Locales::default())->toBe('en')
            ->and(Locales::extra())->toBe(['es', 'fr'])
            ->and(Locales::isMultilingual())->toBeTrue()
            ->and(Locales::label('es'))->toBe('ES');

        config()->set('infinito-onboarding.locale_labels', ['es' => 'Español']);
        expect(Locales::label('es'))->toBe('Español');
    });

    it('falls back to the app locale when nothing is configured', function (): void {
        config()->set('infinito-onboarding.locales', []);
        config()->set('app.locale', 'de');

        expect(Locales::all())->toBe(['de'])
            ->and(Locales::isMultilingual())->toBeFalse()
            ->and(Locales::extra())->toBe([]);
    });
});

describe('translated content', function (): void {
    it('resolves current locale, then fallback, then the base column', function (): void {
        $step = TourStep::factory()->make([
            'title' => 'Export',
            'body' => '<p>English body</p>',
            'translations' => ['es' => ['title' => 'Exportar'], 'fr' => ['title' => 'Exporter', 'body' => '<p>Corps</p>']],
        ]);

        expect($step->translated('title'))->toBe('Export')
            ->and($step->translated('title', 'es'))->toBe('Exportar')
            ->and($step->translated('body', 'es'))->toBe('<p>English body</p>')
            ->and($step->translated('body', 'fr'))->toBe('<p>Corps</p>')
            ->and($step->translated('title', 'de'))->toBe('Export');

        config()->set('infinito-onboarding.fallback_locale', 'fr');
        expect($step->translated('body', 'de'))->toBe('<p>Corps</p>');

        app()->setLocale('es');
        expect($step->translated('title'))->toBe('Exportar');
    });

    it('exposes and sets translations per attribute', function (): void {
        $tour = Tour::factory()->make(['name' => 'Release', 'translations' => ['es' => ['name' => 'Lanzamiento']]]);

        expect($tour->getTranslations('name'))->toBe(['en' => 'Release', 'es' => 'Lanzamiento']);

        $tour->setTranslation('fr', 'name', 'Sortie')->setTranslation('fr', 'unknown', 'x')->setTranslation('en', 'name', 'Base');

        expect($tour->name)->toBe('Base')
            ->and($tour->translations)->toBe(['es' => ['name' => 'Lanzamiento'], 'fr' => ['name' => 'Sortie']]);
    });

    it('cleans empty and unknown translation values', function (): void {
        expect(TourStep::cleanTranslations(['es' => ['title' => '  ', 'body' => '<p>Hola</p>', 'nope' => 'x'], 'en' => ['title' => 'ignored'], 'fr' => []]))
            ->toBe(['es' => ['body' => '<p>Hola</p>']])
            ->and(TourStep::cleanTranslations(['es' => ['title' => '']]))->toBeNull()
            ->and(TourStep::cleanTranslations(null))->toBeNull();
    });

    it('localises the overlay payload and the changelog modal', function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $tour = Tour::factory()->changelog()->create(['name' => 'Release', 'translations' => ['es' => ['name' => 'Lanzamiento']]]);
        TourStep::factory()->for($tour)->untargeted()->create(['title' => 'New filters', 'translations' => ['es' => ['title' => 'Nuevos filtros']]]);

        app()->setLocale('es');

        expect(TourOverlay::payloadFor($tour)['tour']['name'])->toBe('Lanzamiento')
            ->and($tour->steps->first()->toPayload()['title'])->toBe('Nuevos filtros');

        Livewire::test(TourOverlay::class, ['tourId' => $tour->id])
            ->assertSee('Nuevos filtros')
            ->assertSee('Lanzamiento')
            ->assertDontSee('New filters');
    });
});

describe('resource', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['roles' => ['tour-author']]));
    });

    it('saves per-locale tour translations from the tabs and strips empty ones', function (): void {
        Livewire::test(CreateTour::class)
            ->fillForm([
                'name' => 'Release',
                'key' => 'release',
                'translations' => ['es' => ['name' => 'Lanzamiento', 'description' => ''], 'fr' => ['name' => '', 'description' => '']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Tour::query()->where('key', 'release')->first()->translations)->toBe(['es' => ['name' => 'Lanzamiento']]);
    });

    it('saves step translations through the relation manager', function (): void {
        $tour = Tour::factory()->create();

        Livewire::test(StepsRelationManager::class, ['ownerRecord' => $tour, 'pageClass' => EditTour::class])
            ->callTableAction('create', data: [
                'title' => 'Export',
                'target_type' => 'none',
                'placement' => 'auto',
                'translations' => ['es' => ['title' => 'Exportar', 'body' => '<p>Hola</p>'], 'fr' => ['title' => '']],
            ])
            ->assertHasNoTableActionErrors();

        expect($tour->steps()->first()->translations)->toBe(['es' => ['title' => 'Exportar', 'body' => '<p>Hola</p>']]);
    });

    it('renders no translation tabs in single-language apps', function (): void {
        config()->set('infinito-onboarding.locales', []);

        Livewire::test(CreateTour::class)->assertDontSee('Translations');

        config()->set('infinito-onboarding.locales', ['en', 'es']);

        Livewire::test(CreateTour::class)->assertSee('Translations')->assertSee('ES');
    });
});

describe('builder and interchange', function (): void {
    it('translates steps and the tour through the builder', function (): void {
        $tour = Tour::define('i18n')
            ->translateTour('es', ['name' => 'Novedades'])
            ->step('a', 'Export', 'Body')->translate('es', ['title' => 'Exportar'])->translate('es', ['body' => 'Cuerpo'])
            ->note('Bye')
            ->save();

        expect($tour->translations)->toBe(['es' => ['name' => 'Novedades']])
            ->and($tour->steps[0]->translations)->toBe(['es' => ['title' => 'Exportar', 'body' => 'Cuerpo']])
            ->and($tour->steps[1]->translations)->toBeNull()
            ->and($tour->steps[0]->translated('title', 'es'))->toBe('Exportar');
    });

    it('round-trips translations through export and import', function (): void {
        $tour = Tour::define('i18n')
            ->translateTour('es', ['name' => 'Novedades'])
            ->step('a', 'Export')->translate('es', ['title' => 'Exportar'])
            ->save();

        $exported = app(TourExporter::class)->toArray($tour);
        $tour->delete();

        $imported = app(TourImporter::class)->importArray($exported);

        expect($imported->translations)->toBe(['es' => ['name' => 'Novedades']])
            ->and($imported->steps[0]->translations)->toBe(['es' => ['title' => 'Exportar']])
            ->and(app(TourExporter::class)->toArray($imported))->toEqual($exported);
    });
});
