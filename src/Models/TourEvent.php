<?php

namespace Arzcode\InfinitoOnboarding\Models;

use Arzcode\InfinitoOnboarding\Database\Factories\TourEventFactory;
use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analytics event: a view, a highlighted step, a completion, a dismissal or
 * a missing target, per user / tenant / tour version.
 *
 * @property int $id
 * @property int $tour_id
 * @property int|null $step_id
 * @property string $user_id
 * @property string $tenant_id
 * @property string $version
 * @property TourEventType $event
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 * @property-read Tour $tour
 * @property-read TourStep|null $step
 */
class TourEvent extends Model
{
    /** @use HasFactory<TourEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $attributes = [
        'tenant_id' => TourCompletion::NO_TENANT,
    ];

    protected function casts(): array
    {
        return [
            'event' => TourEventType::class,
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('infinito-onboarding.table_names.tour_events', 'onboarding_tour_events');
    }

    protected static function newFactory(): TourEventFactory
    {
        return TourEventFactory::new();
    }

    public function setTenantIdAttribute(?string $value): void
    {
        $this->attributes['tenant_id'] = TourCompletion::normalizeTenantId($value);
    }

    public function setUserIdAttribute(string|int $value): void
    {
        $this->attributes['user_id'] = (string) $value;
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<TourStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(TourStep::class);
    }

    /** @param  Builder<TourEvent>  $query */
    public function scopeOfType(Builder $query, TourEventType $type): Builder
    {
        return $query->where('event', $type);
    }

    /** @param  Builder<TourEvent>  $query */
    public function scopeForVersion(Builder $query, string $version): Builder
    {
        return $query->where('version', $version);
    }
}
