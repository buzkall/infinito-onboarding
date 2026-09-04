<?php

namespace Arzcode\InfinitoOnboarding\Filament\Resources;

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\Pages;
use Arzcode\InfinitoOnboarding\Filament\Resources\TourResource\RelationManagers\StepsRelationManager;
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Support\PanelRoutes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $slug = 'onboarding-tours';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('infinito-onboarding::onboarding.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('infinito-onboarding::onboarding.resource.plural_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::plugin()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    /**
     * Access is gated by the plugin's authorize() closure only; a policy is not
     * required, but if the app registers one for Tour it is honoured too.
     */
    public static function canAccess(): bool
    {
        $plugin = static::plugin();

        if ($plugin === null || ! $plugin->isAuthorized()) {
            return false;
        }

        return parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('infinito-onboarding::onboarding.resource.sections.basics'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('key')) && filled($state)) {
                                    $set('key', Str::slug($state));
                                }
                            }),
                        TextInput::make('key')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.key'))
                            ->helperText(__('infinito-onboarding::onboarding.resource.fields.key_help'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.description'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('mode')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.mode'))
                            ->options(TourMode::class)
                            ->default(TourMode::Tour)
                            ->required()
                            ->native(false),
                        TextInput::make('version')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.version'))
                            ->helperText(__('infinito-onboarding::onboarding.resource.fields.version_help'))
                            ->default('1')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('route_pattern')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.route_pattern'))
                            ->helperText(__('infinito-onboarding::onboarding.resource.fields.route_pattern_help'))
                            ->placeholder('admin/orders*')
                            ->datalist(fn (): array => PanelRoutes::patternsFor(Filament::getCurrentPanel()))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('infinito-onboarding::onboarding.resource.sections.publishing'))
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.is_active'))
                            ->default(true),
                        Toggle::make('is_published')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.is_published'))
                            ->live()
                            ->afterStateHydrated(function (Toggle $component, Get $get): void {
                                $component->state(filled($get('published_at')));
                            })
                            ->afterStateUpdated(function (Set $set, Get $get, bool $state): void {
                                if ($state && blank($get('published_at'))) {
                                    $set('published_at', now()->toDateTimeString());
                                }
                            }),
                        DateTimePicker::make('published_at')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.published_at'))
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => (bool) $get('is_published')),
                        DateTimePicker::make('starts_at')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.starts_at'))
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.ends_at'))
                            ->seconds(false)
                            ->after('starts_at'),
                        TextInput::make('sort')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.sort'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                Section::make(__('infinito-onboarding::onboarding.resource.sections.audience'))
                    ->description(__('infinito-onboarding::onboarding.resource.sections.audience_help'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TagsInput::make('audience.roles')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.audience_roles'))
                            ->suggestions(fn (): array => static::roleSuggestions())
                            ->placeholder('admin'),
                        TagsInput::make('audience.permissions')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.audience_permissions'))
                            ->placeholder('export orders'),
                        TextInput::make('tenant_id')
                            ->label(__('infinito-onboarding::onboarding.resource.fields.tenant_id'))
                            ->helperText(__('infinito-onboarding::onboarding.resource.fields.tenant_id_help'))
                            ->visible(fn (): bool => (bool) Filament::getCurrentPanel()?->hasTenancy())
                            ->default(fn (): ?string => Filament::getTenant()?->getKey() !== null ? (string) Filament::getTenant()->getKey() : null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Tour $record): ?string => $record->route_pattern),
                TextColumn::make('key')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.key'))
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('mode')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.mode'))
                    ->badge(),
                TextColumn::make('version')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.version'))
                    ->sortable(),
                IconColumn::make('published_at')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.is_published'))
                    ->boolean()
                    ->getStateUsing(fn (Tour $record): bool => $record->isPublished())
                    ->tooltip(fn (Tour $record): ?string => $record->published_at?->toDayDateTimeString()),
                TextColumn::make('steps_count')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.steps_count'))
                    ->counts('steps')
                    ->alignCenter(),
                TextColumn::make('completions_count')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.completions_count'))
                    ->counts('completions')
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label(__('infinito-onboarding::onboarding.resource.fields.is_active')),
            ])
            ->filters([
                SelectFilter::make('mode')->options(TourMode::class),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                static::previewAction(),
                EditAction::make(),
                static::resetSeenStateAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('infinito-onboarding::onboarding.resource.actions.preview'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('gray')
            ->url(fn (Tour $record): string => $record->getPreviewUrl())
            ->openUrlInNewTab();
    }

    public static function resetSeenStateAction(): Action
    {
        return Action::make('resetSeenState')
            ->label(__('infinito-onboarding::onboarding.resource.actions.reset_seen_state'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('infinito-onboarding::onboarding.resource.actions.reset_seen_state_confirm'))
            ->action(function (Tour $record): void {
                $deleted = $record->completions()->delete();

                Notification::make()
                    ->title(__('infinito-onboarding::onboarding.resource.actions.reset_seen_state_done', ['count' => $deleted]))
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['steps', 'completions']);
    }

    /**
     * Applied by the create and edit pages before saving: turns the virtual
     * `is_published` toggle into `published_at` and drops empty audience
     * criteria so the JSON column stays null when nothing is restricted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateFormData(array $data): array
    {
        $isPublished = (bool) ($data['is_published'] ?? filled($data['published_at'] ?? null));
        unset($data['is_published']);

        if (! $isPublished) {
            $data['published_at'] = null;
        } elseif (blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        $audience = collect($data['audience'] ?? [])
            ->map(fn (mixed $value): mixed => is_array($value) ? array_values(array_filter($value, fn (mixed $item): bool => filled($item))) : $value)
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        $data['audience'] = $audience === [] ? null : $audience;

        return $data;
    }

    /**
     * @return array<int, string>
     */
    protected static function roleSuggestions(): array
    {
        $roleModel = config('permission.models.role');

        if (! is_string($roleModel) || ! class_exists($roleModel)) {
            return [];
        }

        try {
            return $roleModel::query()->pluck('name')->map(fn ($name): string => (string) $name)->unique()->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function plugin(): ?InfinitoOnboardingPlugin
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('infinito-onboarding')) {
            return null;
        }

        /** @var InfinitoOnboardingPlugin $plugin */
        $plugin = $panel->getPlugin('infinito-onboarding');

        return $plugin;
    }
}
