<?php

namespace Arzcode\InfinitoOnboarding\Models;

use Arzcode\InfinitoOnboarding\Database\Factories\TourStepFactory;
use Arzcode\InfinitoOnboarding\Enums\Placement;
use Arzcode\InfinitoOnboarding\Enums\TargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property int $order
 * @property TargetType $target_type
 * @property string|null $target
 * @property string $title
 * @property string|null $body
 * @property Placement $placement
 * @property array<string, mixed>|null $extra
 * @property-read Tour $tour
 */
class TourStep extends Model
{
    /** @use HasFactory<TourStepFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'order' => 0,
        'target_type' => 'data_tour',
        'placement' => 'auto',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'target_type' => TargetType::class,
            'placement' => Placement::class,
            'extra' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('infinito-onboarding.table_names.tour_steps', 'onboarding_tour_steps');
    }

    protected static function newFactory(): TourStepFactory
    {
        return TourStepFactory::new();
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * The CSS selector the browser should query for this step, or null for a
     * centred (untargeted) step.
     */
    public function getSelector(): ?string
    {
        return $this->target_type->toSelector($this->target);
    }

    /**
     * Actions run before the step is shown (open a modal, switch a tab…),
     * stored in `extra.before` as `[{type: click|wait|dispatch, target_type,
     * target, selector, timeout, event}]`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBeforeActions(): array
    {
        $actions = $this->extra['before'] ?? [];

        if (! is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->filter(fn (mixed $action): bool => is_array($action))
            ->map(fn (array $action): array => [
                'type' => (string) ($action['type'] ?? 'click'),
                'target_type' => isset($action['target_type']) ? (string) $action['target_type'] : null,
                'target' => isset($action['target']) ? (string) $action['target'] : null,
                'selector' => isset($action['selector']) ? (string) $action['selector'] : null,
                'timeout' => isset($action['timeout']) ? (int) $action['timeout'] : null,
                'event' => isset($action['event']) ? (string) $action['event'] : null,
            ])
            ->filter(fn (array $action): bool => filled($action['target']) || filled($action['selector']) || filled($action['event']) || $action['type'] === 'wait')
            ->values()
            ->all();
    }

    /**
     * Whether clicking the highlighted element (e.g. a wire:click button)
     * advances the tour.
     */
    public function advancesOnClick(): bool
    {
        return (bool) ($this->extra['advance_on_click'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'target_type' => $this->target_type->value,
            'target' => $this->target,
            'selector' => $this->getSelector(),
            'title' => $this->title,
            'body' => $this->body,
            'placement' => $this->placement->value,
            'before' => $this->getBeforeActions(),
            'advance_on_click' => $this->advancesOnClick(),
            'extra' => $this->extra ?? [],
        ];
    }
}
