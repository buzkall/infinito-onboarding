<?php

namespace Workbench\App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrdersDemo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $slug = 'orders';

    protected static ?string $title = 'Orders';

    protected string $view = 'workbench::filament.pages.orders-demo';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['customer' => 'Ada Lovelace', 'status' => 'paid', 'shipped_at' => now()->toDateString(), 'priority' => true]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order #1042')
                    ->description('Everything about this order in one place.')
                    ->tourTarget('order-section')
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer')->tourTarget('customer-field'),
                        Select::make('status')->options(['draft' => 'Draft', 'paid' => 'Paid', 'shipped' => 'Shipped'])->native(false)->tourTarget('status-field'),
                        DatePicker::make('shipped_at')->tourTarget('shipped-field'),
                        Toggle::make('priority')->label('Priority shipping')->tourTarget('priority-field'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export orders')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')->tourTarget('export-orders'),
            Action::make('bulk')->label('Bulk actions')->icon(Heroicon::OutlinedSquares2x2)->tourTarget('bulk-actions'),
        ];
    }
}
