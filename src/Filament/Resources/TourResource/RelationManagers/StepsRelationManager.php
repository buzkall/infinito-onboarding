<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers;

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Filament\Support\TranslationTabs;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
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
                ...TranslationTabs::make([
                    'title' => fn (string $path, string $locale): TextInput => TextInput::make($path)
                        ->label(__('infinito-onboarding::onboarding.resource.steps.fields.title'))
                        ->maxLength(255),
                    'body' => fn (string $path, string $locale): RichEditor => RichEditor::make($path)
                        ->label(__('infinito-onboarding::onboarding.resource.steps.fields.body'))
                        ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'underline']),
                ]),
                Toggle::make('extra.advance_on_click')
                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.advance_on_click'))
                    ->helperText(__('infinito-onboarding::onboarding.resource.steps.fields.advance_on_click_help'))
                    ->default(false),
                Section::make(__('infinito-onboarding::onboarding.resource.steps.fields.before'))
                    ->description(__('infinito-onboarding::onboarding.resource.steps.fields.before_help'))
                    ->collapsible()
                    ->collapsed(fn (?TourStep $record): bool => $record === null || $record->getBeforeActions() === [])
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('extra.before')
                            ->hiddenLabel()
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel(__('infinito-onboarding::onboarding.resource.steps.fields.before_add'))
                            ->schema([
                                Select::make('type')
                                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.before_type'))
                                    ->options([
                                        'click' => __('infinito-onboarding::onboarding.resource.steps.before_types.click'),
                                        'wait' => __('infinito-onboarding::onboarding.resource.steps.before_types.wait'),
                                    ])
                                    ->default('click')
                                    ->required()
                                    ->native(false),
                                TextInput::make('target')
                                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.before_target'))
                                    ->helperText(__('infinito-onboarding::onboarding.resource.steps.fields.before_target_help'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('timeout')
                                    ->label(__('infinito-onboarding::onboarding.resource.steps.fields.before_timeout'))
                                    ->numeric()
                                    ->default(2000)
                                    ->minValue(0)
                                    ->suffix('ms'),
                            ])
                            ->mutateDehydratedStateUsing(fn (?array $state): array => collect($state ?? [])
                                ->map(function (array $action): array {
                                    $target = (string) ($action['target'] ?? '');
                                    $isSelector = (bool) preg_match('/[#.\[\]>:\s]/', $target);

                                    return [
                                        'type' => $action['type'] ?? 'click',
                                        'target_type' => $isSelector ? TargetType::Css->value : TargetType::DataTour->value,
                                        'target' => $target,
                                        'timeout' => (int) ($action['timeout'] ?? 2000),
                                    ];
                                })
                                ->values()
                                ->all()),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function cleanStepData(array $data): array
    {
        if (array_key_exists('translations', $data)) {
            $data['translations'] = TourStep::cleanTranslations(is_array($data['translations']) ? $data['translations'] : null);
        }

        return $data;
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

                        return static::cleanStepData($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data): array => static::cleanStepData($data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
