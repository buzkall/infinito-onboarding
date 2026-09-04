<?php

namespace Arzcode\InfinitoOnboarding\Models;

use Arzcode\InfinitoOnboarding\Database\Factories\TourCompletionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tour_id
 * @property string $user_id
 * @property string $tenant_id
 * @property string $seen_version
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $dismissed_at
 * @property-read Tour $tour
 */
class TourCompletion extends Model
{
    /** @use HasFactory<TourCompletionFactory> */
    use HasFactory;

    /**
     * Tenant-less completions are stored with an empty string so the unique
     * index (tour, user, tenant, version) works on every database engine.
     */
    public const NO_TENANT = '';

    protected $guarded = [];

    protected $attributes = [
        'tenant_id' => self::NO_TENANT,
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('infinito-onboarding.table_names.tour_completions', 'onboarding_tour_completions');
    }

    protected static function newFactory(): TourCompletionFactory
    {
        return TourCompletionFactory::new();
    }

    public static function normalizeTenantId(?string $tenantId): string
    {
        return filled($tenantId) ? (string) $tenantId : self::NO_TENANT;
    }

    public function setTenantIdAttribute(?string $value): void
    {
        $this->attributes['tenant_id'] = static::normalizeTenantId($value);
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

    /** @param  Builder<TourCompletion>  $query */
    public function scopeForUser(Builder $query, string|int $userId, ?string $tenantId = null): Builder
    {
        return $query
            ->where('user_id', (string) $userId)
            ->where('tenant_id', static::normalizeTenantId($tenantId));
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isDismissed(): bool
    {
        return $this->dismissed_at !== null;
    }
}
