<?php

namespace Arzcode\InfinitoOnboarding\Models;

use Arzcode\InfinitoOnboarding\Database\Factories\TourFactory;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property TourMode $mode
 * @property string|null $route_pattern
 * @property string $version
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property array<string, mixed>|null $audience
 * @property string|null $tenant_id
 * @property int $sort
 * @property bool $is_active
 * @property-read Collection<int, TourStep> $steps
 * @property-read Collection<int, TourCompletion> $completions
 */
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'mode' => 'tour',
        'version' => '1',
        'sort' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'mode' => TourMode::class,
            'published_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'audience' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('infinito-onboarding.table_names.tours', 'onboarding_tours');
    }

    protected static function newFactory(): TourFactory
    {
        return TourFactory::new();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return HasMany<TourStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(TourStep::class)->orderBy('order');
    }

    /** @return HasMany<TourCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(TourCompletion::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** @param  Builder<Tour>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<Tour>  $query */
    public function scopePublished(Builder $query, ?Carbon $now = null): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now ?? now());
    }

    /** @param  Builder<Tour>  $query */
    public function scopeWithinWindow(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Restrict to tours whose route pattern matches the given route.
     *
     * Wildcards are evaluated in PHP (Str::is), so the query only narrows down
     * candidates; the definitive check happens through matchesRoute().
     *
     * @param  Builder<Tour>  $query
     */
    public function scopeForRoute(Builder $query, string $route): Builder
    {
        return $query->where(function (Builder $query) use ($route): void {
            $query
                ->whereNull('route_pattern')
                ->orWhere('route_pattern', '')
                ->orWhere('route_pattern', '*')
                ->orWhere('route_pattern', $route)
                ->orWhere('route_pattern', 'like', '%*%');
        });
    }

    /** @param  Builder<Tour>  $query */
    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where(function (Builder $query) use ($tenantId): void {
            $query->whereNull('tenant_id');

            if (filled($tenantId)) {
                $query->orWhere('tenant_id', $tenantId);
            }
        });
    }

    /** @param  Builder<Tour>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /** @param  Builder<Tour>  $query */
    public function scopeMode(Builder $query, TourMode $mode): Builder
    {
        return $query->where('mode', $mode);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function matchesRoute(string $route): bool
    {
        if (blank($this->route_pattern)) {
            return true;
        }

        $route = ltrim($route, '/');

        return collect(explode(',', $this->route_pattern))
            ->map(fn (string $pattern) => ltrim(trim($pattern), '/'))
            ->filter()
            ->contains(fn (string $pattern) => Str::is($pattern, $route));
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }

    public function isWithinWindow(): bool
    {
        $now = now();

        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }

    public function isChangelog(): bool
    {
        return $this->mode === TourMode::Changelog;
    }

    public function isSeenBy(string|int $userId, ?string $tenantId = null): bool
    {
        return $this->completions()
            ->forUser($userId, $tenantId)
            ->where('seen_version', $this->version)
            ->exists();
    }
}
