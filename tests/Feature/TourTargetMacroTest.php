<?php

use Arzcode\InfinitoOnboarding\Macros\TourTargetMacro;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\Livewire\FormFixture;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\Livewire\TableFixture;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\View\ComponentSlot;
use Livewire\Livewire;

it('registers the macro on every supported component class', function (): void {
    expect(Action::hasMacro('tourTarget'))->toBeTrue()
        ->and(TextInput::hasMacro('tourTarget'))->toBeTrue()
        ->and(TextColumn::hasMacro('tourTarget'))->toBeTrue()
        ->and(NavigationItem::hasMacro('tourTarget'))->toBeTrue()
        ->and(TextEntry::hasMacro('tourTarget'))->toBeTrue()
        ->and(Section::hasMacro('tourTarget'))->toBeTrue();
});

it('is idempotent when registered twice', function (): void {
    TourTargetMacro::register();
    TourTargetMacro::register();

    expect(Action::make('x')->tourTarget('x')->getExtraAttributes())->toBe(['data-tour' => 'x']);
});

it('sanitises keys so they are safe inside an attribute', function (): void {
    expect(TourTargetMacro::sanitizeKey(' export"><script> '))->toBe('export---script-')
        ->and(TourTargetMacro::sanitizeKey('orders.export:btn_1'))->toBe('orders.export:btn_1');
});

it('keeps previously set extra attributes on actions', function (): void {
    $action = Action::make('export')->extraAttributes(['class' => 'foo'])->tourTarget('export');

    expect($action->getExtraAttributes())->toMatchArray(['class' => 'foo', 'data-tour' => 'export']);
});

it('renders data-tour on a form field wrapper and a layout section', function (): void {
    Livewire::test(FormFixture::class)
        ->assertSeeHtml('data-tour="email-field"')
        ->assertSeeHtml('data-tour="role-field"')
        ->assertSeeHtml('data-tour="profile-section"');
});

it('renders data-tour on a table column header and a header action', function (): void {
    User::factory()->count(2)->create();

    Livewire::test(TableFixture::class)
        ->assertSeeHtml('data-tour="name-column"')
        ->assertSeeHtml('data-tour="export-action"');
});

it('renders data-tour exactly once for a table column regardless of row count', function (): void {
    User::factory()->count(3)->create();

    $html = Livewire::test(TableFixture::class)->html();

    expect(substr_count($html, 'data-tour="name-column"'))->toBe(1);
});

it('renders data-tour on a standalone action button', function (): void {
    $html = Action::make('export')->label('Export')->tourTarget('export-btn')->toHtml();

    expect($html)->toContain('data-tour="export-btn"');
});

it('puts data-tour on the navigation item attribute bag', function (): void {
    $item = NavigationItem::make('Orders')->url('/admin/orders')->tourTarget('nav-orders');

    expect($item->getExtraAttributes())->toBe(['data-tour' => 'nav-orders']);
});

it('renders data-tour on a sidebar navigation item', function (): void {
    $item = NavigationItem::make('Orders')->url('/admin/orders')->tourTarget('nav-orders');

    $html = view('filament-panels::components.sidebar.item', [
        'active' => false,
        'activeChildItems' => false,
        'badge' => null,
        'badgeColor' => null,
        'badgeTooltip' => null,
        'childItems' => [],
        'first' => true,
        'grouped' => false,
        'icon' => null,
        'last' => true,
        'shouldOpenUrlInNewTab' => false,
        'sidebarCollapsible' => false,
        'url' => '/admin/orders',
        'attributes' => $item->getExtraAttributeBag(),
        'slot' => new ComponentSlot('Orders'),
    ])->render();

    expect($html)->toContain('data-tour="nav-orders"');
})->skip(fn () => ! view()->exists('filament-panels::components.sidebar.item'), 'sidebar item view not available');

it('targets the entry wrapper for infolist entries', function (): void {
    $entry = TextEntry::make('status')->tourTarget('status-entry');

    expect($entry->getExtraEntryWrapperAttributes())->toBe(['data-tour' => 'status-entry']);
});

it('targets the field wrapper, not the input, for form fields', function (): void {
    $field = TextInput::make('email')->tourTarget('email');

    expect($field->getExtraFieldWrapperAttributes())->toBe(['data-tour' => 'email'])
        ->and($field->getExtraInputAttributes())->toBe([])
        ->and($field->getExtraAttributes())->toBe([]);
});
