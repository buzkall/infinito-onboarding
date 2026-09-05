<?php

use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages\EditTour;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers\StepsRelationManager;
use Arzcode\InfinitoOnboarding\Livewire\TourRecorder;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

describe('model', function (): void {
    it('normalises before actions and exposes them in the payload', function (): void {
        $step = TourStep::factory()->make(['extra' => [
            'before' => [
                ['type' => 'click', 'target_type' => 'data_tour', 'target' => 'open-settings', 'timeout' => '1500'],
                ['type' => 'wait', 'selector' => '#modal', 'timeout' => 3000],
                ['type' => 'dispatch', 'event' => 'open-modal'],
                'garbage',
                ['type' => 'click'],
            ],
            'advance_on_click' => 1,
        ]]);

        $payload = $step->toPayload();

        expect($step->getBeforeActions())->toHaveCount(3)
            ->and($step->getBeforeActions()[0])->toMatchArray(['type' => 'click', 'target_type' => 'data_tour', 'target' => 'open-settings', 'timeout' => 1500])
            ->and($step->getBeforeActions()[1])->toMatchArray(['type' => 'wait', 'selector' => '#modal', 'timeout' => 3000])
            ->and($step->getBeforeActions()[2])->toMatchArray(['type' => 'dispatch', 'event' => 'open-modal'])
            ->and($step->advancesOnClick())->toBeTrue()
            ->and($payload['before'])->toHaveCount(3)
            ->and($payload['advance_on_click'])->toBeTrue();
    });

    it('defaults to no preconditions', function (): void {
        $step = TourStep::factory()->make();

        expect($step->getBeforeActions())->toBe([])
            ->and($step->advancesOnClick())->toBeFalse()
            ->and($step->toPayload()['before'])->toBe([]);
    });
});

describe('builder', function (): void {
    it('attaches preconditions and advance-on-click to the last step', function (): void {
        $tour = Tour::define('gated')
            ->step('open-settings', 'Open settings')->advanceOnClick()
            ->step('inside-modal', 'Inside')->clickFirst('open-settings')->waitFor('#settings-modal', 5000)
            ->note('Done')->dispatchFirst('open-modal', ['id' => 'x'])
            ->save();

        $steps = $tour->steps;

        expect($steps[0]->advancesOnClick())->toBeTrue()
            ->and($steps[0]->getBeforeActions())->toBe([])
            ->and($steps[1]->getBeforeActions())->toHaveCount(2)
            ->and($steps[1]->getBeforeActions()[0])->toMatchArray(['type' => 'click', 'target_type' => 'data_tour', 'target' => 'open-settings', 'timeout' => 2000])
            ->and($steps[1]->getBeforeActions()[1])->toMatchArray(['type' => 'wait', 'target_type' => 'css', 'target' => '#settings-modal', 'timeout' => 5000])
            ->and($steps[2]->getBeforeActions()[0])->toMatchArray(['type' => 'dispatch', 'event' => 'open-modal']);
    });

    it('refuses step options before any step exists', function (): void {
        expect(fn () => Tour::define('x')->clickFirst('a'))->toThrow(LogicException::class);
    });
});

describe('resource', function (): void {
    it('stores preconditions from the steps relation manager repeater', function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['roles' => ['tour-author']]));
        $tour = Tour::factory()->create();

        Livewire::test(StepsRelationManager::class, ['ownerRecord' => $tour, 'pageClass' => EditTour::class])
            ->callTableAction('create', data: [
                'title' => 'Inside the modal',
                'target_type' => TargetType::DataTour->value,
                'target' => 'inside-modal',
                'placement' => 'auto',
                'extra' => [
                    'advance_on_click' => true,
                    'before' => [
                        ['type' => 'click', 'target' => 'open-settings', 'timeout' => 1000],
                        ['type' => 'wait', 'target' => '#settings .body', 'timeout' => 4000],
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $step = $tour->steps()->first();

        expect($step->advancesOnClick())->toBeTrue()
            ->and($step->getBeforeActions()[0])->toMatchArray(['type' => 'click', 'target_type' => 'data_tour', 'target' => 'open-settings', 'timeout' => 1000])
            ->and($step->getBeforeActions()[1])->toMatchArray(['type' => 'wait', 'target_type' => 'css', 'target' => '#settings .body', 'timeout' => 4000]);
    });
});

describe('recorder', function (): void {
    it('persists preconditions captured in record mode', function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['roles' => ['tour-author']]));
        $tour = Tour::factory()->create();

        $payload = Livewire::test(TourRecorder::class, ['tourId' => $tour->id])
            ->instance()
            ->saveSteps([[
                'title' => 'Inside',
                'target_type' => 'data_tour',
                'target' => 'inside-modal',
                'extra' => [
                    'strategy' => 'data-tour',
                    'score' => 'green',
                    'before' => [['type' => 'click', 'target_type' => 'data_tour', 'target' => 'open-settings', 'selector' => '[data-tour="open-settings"]', 'timeout' => null]],
                    'advance_on_click' => false,
                ],
            ]]);

        expect($payload[0]['before'])->toHaveCount(1)
            ->and($payload[0]['before'][0]['target'])->toBe('open-settings')
            ->and($tour->steps()->first()->getBeforeActions()[0]['selector'])->toBe('[data-tour="open-settings"]');
    });
});
