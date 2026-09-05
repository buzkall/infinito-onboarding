<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Code-first tour authoring, so tours can live in git:
 *
 *   Tour::define('q3-2026')
 *       ->route('admin/orders*')
 *       ->version('2.4.0')
 *       ->step('export-btn', 'Export orders', 'You can now export…')
 *       ->publish()
 *       ->save();
 *
 * save() syncs idempotently into the database by key; running it again with
 * the same definition changes nothing, running it with a changed definition
 * updates the tour and replaces its steps.
 */
class TourDefinition
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<int, array<string, mixed>> */
    protected array $steps = [];

    final public function __construct(protected string $key)
    {
        $this->attributes = [
            'name' => Str::headline($key),
            'mode' => TourMode::Tour,
            'version' => '1',
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * Build a definition from the JSON interchange format (see TourExporter).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $key = (string) ($data['key'] ?? '');

        if ($key === '') {
            throw new \InvalidArgumentException('A tour definition needs a "key".');
        }

        $definition = new static($key);

        foreach (['name', 'description', 'route_pattern', 'version', 'audience', 'tenant_id', 'sort', 'is_active', 'translations'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $definition->attributes[$attribute] = $data[$attribute];
            }
        }

        if (isset($data['mode'])) {
            $definition->mode($data['mode'] instanceof TourMode ? $data['mode'] : TourMode::from((string) $data['mode']));
        }

        foreach (['published_at', 'starts_at', 'ends_at'] as $date) {
            if (array_key_exists($date, $data)) {
                $definition->attributes[$date] = filled($data[$date]) ? Carbon::parse($data[$date]) : null;
            }
        }

        foreach ($data['steps'] ?? [] as $step) {
            $definition->addStep(
                targetType: $step['target_type'] instanceof TargetType ? $step['target_type'] : TargetType::from((string) ($step['target_type'] ?? 'data_tour')),
                target: $step['target'] ?? null,
                title: (string) ($step['title'] ?? ''),
                body: $step['body'] ?? null,
                placement: isset($step['placement']) ? ($step['placement'] instanceof Placement ? $step['placement'] : Placement::from((string) $step['placement'])) : Placement::Auto,
                extra: $step['extra'] ?? null,
                translations: is_array($step['translations'] ?? null) ? $step['translations'] : null,
            );
        }

        return $definition;
    }

    /*
    |--------------------------------------------------------------------------
    | Tour attributes
    |--------------------------------------------------------------------------
    */

    public function name(string $name): static
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    public function mode(TourMode $mode): static
    {
        $this->attributes['mode'] = $mode;

        return $this;
    }

    public function changelog(): static
    {
        return $this->mode(TourMode::Changelog);
    }

    /**
     * Hint mode: persistent pulsing beacons next to each step's target.
     */
    public function hints(): static
    {
        return $this->mode(TourMode::Hint);
    }

    /**
     * Path pattern(s) the tour runs on, e.g. `admin/orders*`. Pass several to
     * match any of them.
     */
    public function route(string ...$patterns): static
    {
        $this->attributes['route_pattern'] = $patterns === [] ? null : implode(',', $patterns);

        return $this;
    }

    public function version(string $version): static
    {
        $this->attributes['version'] = $version;

        return $this;
    }

    public function publish(DateTimeInterface|string|null $at = null): static
    {
        $this->attributes['published_at'] = $at === null ? now() : Carbon::parse($at);

        return $this;
    }

    public function unpublish(): static
    {
        $this->attributes['published_at'] = null;

        return $this;
    }

    public function between(DateTimeInterface|string|null $startsAt, DateTimeInterface|string|null $endsAt): static
    {
        $this->attributes['starts_at'] = $startsAt === null ? null : Carbon::parse($startsAt);
        $this->attributes['ends_at'] = $endsAt === null ? null : Carbon::parse($endsAt);

        return $this;
    }

    /**
     * @param  array<int, string>|string  $roles
     */
    public function roles(array|string $roles): static
    {
        $this->attributes['audience'] = [...($this->attributes['audience'] ?? []), 'roles' => (array) $roles];

        return $this;
    }

    /**
     * @param  array<int, string>|string  $permissions
     */
    public function permissions(array|string $permissions): static
    {
        $this->attributes['audience'] = [...($this->attributes['audience'] ?? []), 'permissions' => (array) $permissions];

        return $this;
    }

    /**
     * @param  array<int, string|int>|string|int  $users
     */
    public function users(array|string|int $users): static
    {
        $this->attributes['audience'] = [...($this->attributes['audience'] ?? []), 'users' => array_map('strval', (array) $users)];

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $audience
     */
    public function audience(?array $audience): static
    {
        $this->attributes['audience'] = $audience;

        return $this;
    }

    public function tenant(string|int|null $tenantId): static
    {
        $this->attributes['tenant_id'] = $tenantId === null ? null : (string) $tenantId;

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->attributes['sort'] = $sort;

        return $this;
    }

    public function active(bool $active = true): static
    {
        $this->attributes['is_active'] = $active;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Steps
    |--------------------------------------------------------------------------
    */

    /**
     * A step targeting a `->tourTarget('key')` component.
     */
    public function step(string $target, string $title, ?string $body = null, Placement|string $placement = Placement::Auto): static
    {
        return $this->addStep(TargetType::DataTour, $target, $title, $body, $placement);
    }

    /**
     * A step targeting a raw CSS selector (fragile; prefer step()).
     */
    public function cssStep(string $selector, string $title, ?string $body = null, Placement|string $placement = Placement::Auto): static
    {
        return $this->addStep(TargetType::Css, $selector, $title, $body, $placement);
    }

    /**
     * A centred step with no target (also used for changelog entries).
     */
    public function note(string $title, ?string $body = null): static
    {
        return $this->addStep(TargetType::None, null, $title, $body, Placement::Auto);
    }

    /**
     * @param  array<string, mixed>|null  $extra
     * @param  array<string, array<string, string|null>>|null  $translations
     */
    public function addStep(
        TargetType $targetType,
        ?string $target,
        string $title,
        ?string $body = null,
        Placement|string $placement = Placement::Auto,
        ?array $extra = null,
        ?array $translations = null,
    ): static {
        $this->steps[] = [
            'target_type' => $targetType,
            'target' => $targetType === TargetType::None ? null : $target,
            'title' => $title,
            'body' => $body,
            'placement' => $placement instanceof Placement ? $placement : Placement::from($placement),
            'extra' => $extra,
            'translations' => TourStep::cleanTranslations($translations),
        ];

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Step options (apply to the last added step)
    |--------------------------------------------------------------------------
    */

    /**
     * Click an element before showing the last step, e.g. to open the modal
     * or tab that contains its target. Accepts a `->tourTarget()` key or a CSS
     * selector (anything containing a CSS token such as `#`, `.`, `[` or a space).
     */
    public function clickFirst(string $target, int $timeout = 2000): static
    {
        return $this->before([[
            'type' => 'click',
            ...$this->targetPair($target),
            'timeout' => $timeout,
        ]]);
    }

    /**
     * Wait for an element to appear before showing the last step.
     */
    public function waitFor(string $target, int $timeout = 2000): static
    {
        return $this->before([[
            'type' => 'wait',
            ...$this->targetPair($target),
            'timeout' => $timeout,
        ]]);
    }

    /**
     * Dispatch a browser event before showing the last step, e.g.
     * `->dispatchFirst('open-modal', ['id' => 'settings'])`.
     *
     * @param  array<string, mixed>  $detail
     */
    public function dispatchFirst(string $event, array $detail = [], int $timeout = 2000): static
    {
        return $this->before([[
            'type' => 'dispatch',
            'event' => $event,
            'detail' => $detail,
            'timeout' => $timeout,
        ]]);
    }

    /**
     * Append raw precondition actions to the last step.
     *
     * @param  array<int, array<string, mixed>>  $actions
     */
    public function before(array $actions): static
    {
        $step = &$this->lastStep();
        $step['extra'] = [...($step['extra'] ?? []), 'before' => [...($step['extra']['before'] ?? []), ...$actions]];

        return $this;
    }

    /**
     * Translate the last step: `->translate('es', ['title' => 'Exportar', 'body' => '…'])`.
     *
     * @param  array<string, string|null>  $values
     */
    public function translate(string $locale, array $values): static
    {
        $step = &$this->lastStep();
        $step['translations'] = TourStep::cleanTranslations([
            ...($step['translations'] ?? []),
            $locale => [...(($step['translations'] ?? [])[$locale] ?? []), ...$values],
        ]);

        return $this;
    }

    /**
     * Translate the tour's name / description.
     *
     * @param  array<string, string|null>  $values
     */
    public function translateTour(string $locale, array $values): static
    {
        $this->attributes['translations'] = Tour::cleanTranslations([
            ...($this->attributes['translations'] ?? []),
            $locale => [...(($this->attributes['translations'] ?? [])[$locale] ?? []), ...$values],
        ]);

        return $this;
    }

    /**
     * Clicking the highlighted element (a wire:click button, a link…)
     * advances the tour instead of being blocked by the overlay.
     */
    public function advanceOnClick(bool $condition = true): static
    {
        $step = &$this->lastStep();
        $step['extra'] = [...($step['extra'] ?? []), 'advance_on_click' => $condition];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function &lastStep(): array
    {
        if ($this->steps === []) {
            throw new \LogicException('Add a step before configuring step options.');
        }

        return $this->steps[array_key_last($this->steps)];
    }

    /**
     * @return array<string, string>
     */
    protected function targetPair(string $target): array
    {
        $looksLikeSelector = (bool) preg_match('/[#.\[\]>:\s]/', $target);

        return $looksLikeSelector
            ? ['target_type' => TargetType::Css->value, 'target' => $target]
            : ['target_type' => TargetType::DataTour->value, 'target' => $target];
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    */

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Upsert the tour by key and replace its steps. Completions are kept, so
     * bump version() when users should see the tour again.
     */
    public function save(): Tour
    {
        return DB::transaction(function (): Tour {
            /** @var Tour $tour */
            $tour = Tour::query()->updateOrCreate(['key' => $this->key], $this->attributes);

            $tour->steps()->delete();

            foreach ($this->steps as $index => $step) {
                $tour->steps()->create([
                    'order' => $index + 1,
                    'target_type' => $step['target_type'],
                    'target' => $step['target'],
                    'title' => $step['title'],
                    'body' => $step['body'],
                    'placement' => $step['placement'],
                    'extra' => $step['extra'],
                    'translations' => $step['translations'] ?? null,
                ]);
            }

            return $tour->load('steps');
        });
    }
}
