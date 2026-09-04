<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers;

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('infinito-onboarding::onboarding.resource.steps.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('target_type')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.target_type'))
                    ->options(TargetType::class)
                    ->default(TargetType::DataTour)
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('target')
                    ->label(fn (Get $get): string => $get('target_type') === TargetType::Css->value || $get('target_type') === TargetType::Css
                        ? __('infinito-onboarding::onboarding.resource.steps.fields.target_css')
                        : __('infinito-onboarding::onboarding.resource.steps.fields.target_data_tour'))
                    ->helperText(fn (Get $get): string => $get('target_type') === TargetType::Css->value || $get('target_type') === TargetType::Css
                        ? __('infinito-onboarding::onboarding.resource.steps.fields.target_css_help')
                        : __('infinito-onboarding::onboarding.resource.steps.fields.target_data_tour_help'))
                    ->placeholder(fn (Get $get): string => $get('target_type') === TargetType::Css->value || $get('target_type') === TargetType::Css
                        ? '#orders-table thead'
                        : 'export-orders')
                    ->required(fn (Get $get): bool => $get('target_type') !== TargetType::None->value && $get('target_type') !== TargetType::None)
                    ->visible(fn (Get $get): bool => $get('target_type') !== TargetType::None->value && $get('target_type') !== TargetType::None)
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.body'))
                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'underline'])
                    ->columnSpanFull(),
                Select::make('placement')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.placement'))
                    ->options(Placement::class)
                    ->default(Placement::Auto)
                    ->required()
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.title'))
                    ->searchable(),
                TextColumn::make('target_type')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.target_type'))
                    ->badge(),
                TextColumn::make('target')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.target'))
                    ->fontFamily('mono')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('placement')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.placement'))
                    ->badge()
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['order'] ??= ((int) $this->getRelationship()->max('order')) + 1;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
