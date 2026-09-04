<?php

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Models\TourStep;

it('builds a data-tour selector for data_tour targets', function (): void {
    $step = TourStep::factory()->make(['target_type' => TargetType::DataTour, 'target' => 'export-btn']);

    expect($step->getSelector())->toBe('[data-tour="export-btn"]');
});

it('passes css targets through untouched', function (): void {
    $step = TourStep::factory()->css('#orders-table > thead')->make();

    expect($step->getSelector())->toBe('#orders-table > thead');
});

it('has no selector for untargeted steps', function (): void {
    $step = TourStep::factory()->untargeted()->make();

    expect($step->getSelector())->toBeNull()
        ->and($step->target_type->requiresTarget())->toBeFalse();
});

it('casts target_type and placement to enums', function (): void {
    $step = TourStep::factory()->create(['target_type' => 'css', 'target' => '.foo', 'placement' => 'left']);
    $step->refresh();

    expect($step->target_type)->toBe(TargetType::Css)
        ->and($step->placement)->toBe(Placement::Left);
});

it('serialises to the payload the browser expects', function (): void {
    $step = TourStep::factory()->create([
        'order' => 2,
        'target' => 'export-btn',
        'title' => 'Export',
        'body' => '<p>Hi</p>',
        'placement' => Placement::Bottom,
        'extra' => ['foo' => 'bar'],
    ]);

    expect($step->toPayload())->toMatchArray([
        'order' => 2,
        'target_type' => 'data_tour',
        'target' => 'export-btn',
        'selector' => '[data-tour="export-btn"]',
        'title' => 'Export',
        'body' => '<p>Hi</p>',
        'placement' => 'bottom',
        'extra' => ['foo' => 'bar'],
    ]);
});
