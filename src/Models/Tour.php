<?php

namespace Arzcode\InfinitoOnboarding\Models;

use Arzcode\InfinitoOnboarding\Concerns\HasTranslatedContent;
use Arzcode\InfinitoOnboarding\Database\Factories\TourFactory;
use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Support\TourDefinition;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
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
 * @property array<string, array<string, string|null>>|null $translations
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

    use HasTranslatedContent;

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
            'translations' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('infinito-onboarding.table_names.tours', 'onboarding_tours');
    }

    /**
     * @return array<int, string>
     */
    public function translatableAttributes(): array
    {
        return ['name', 'description'];
    }

    protected static function newFactory(): TourFactory
    {
        return TourFactory::new();
    }

    /**
     * Code-first authoring: builds a definition that syncs into the database
     * by key when save() is called.
     */
    public static function define(string $key): TourDefinition
    {
        return TourDefinition::make($key);
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
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<Tour>  $query */
    protected function scopePublished(Builder $query, ?Carbon $now = null): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now ?? now());
    }

    /** @param  Builder<Tour>  $query */
    protected function scopeWithinWindow(Builder $query, ?Carbon $now = null): Builder
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
    protected function scopeForRoute(Builder $query, string $route): Builder
    {
        $route = ltrim($route, '/');

        // Comma-separated lists and leading slashes are resolved by matchesRoute().
        return $query->where(function (Builder $query) use ($route): void {
            $query
                ->whereNull('route_pattern')
                ->orWhere('route_pattern', '')
                ->orWhere('route_pattern', '*')
                ->orWhere('route_pattern', $route)
                ->orWhere('route_pattern', '/' . $route)
                ->orWhere('route_pattern', 'like', '%*%')
                ->orWhere('route_pattern', 'like', '%,%');
        });
    }

    /** @param  Builder<Tour>  $query */
    protected function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where(function (Builder $query) use ($tenantId): void {
            $query->whereNull('tenant_id');

            if (filled($tenantId)) {
                $query->orWhere('tenant_id', $tenantId);
            }
        });
    }

    /**
     * Tours an author may manage: inside a tenant only that tenant's own
     * tours, so one tenant can never edit what another tenant's users see.
     *
     * @param  Builder<Tour>  $query
     */
    protected function scopeManageableIn(Builder $query, ?string $tenantId): Builder
    {
        return $query->when(filled($tenantId), fn (Builder $query) => $query->where('tenant_id', $tenantId));
    }

    /** @param  Builder<Tour>  $query */
    protected function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /** @param  Builder<Tour>  $query */
    protected function scopeMode(Builder $query, TourMode $mode): Builder
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
            ->map(fn (string $pattern): string => ltrim(trim($pattern), '/'))
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

    /**
     * A URL where the tour can be previewed: the route pattern up to its first
     * wildcard (or the panel root when there is none), with the preview flag.
     */
    public function getPreviewUrl(): string
    {
        $parameter = (string) config('infinito-onboarding.query_parameters.preview', 'onboarding-preview');

        return static::pageUrlWith($this->getPagePath(), $parameter, $this->key);
    }

    /**
     * Record mode on the given page path, or on the page derived from the
     * route pattern when none is given.
     */
    public function getRecordUrl(?string $path = null): string
    {
        $parameter = (string) config('infinito-onboarding.query_parameters.record', 'onboarding-record');

        return static::pageUrlWith($path ?? $this->getPagePath(), $parameter, $this->key);
    }

    /**
     * The page the route pattern points at: the first pattern up to its
     * first wildcard, or the panel root when there is no pattern.
     */
    public function getPagePath(): string
    {
        $path = (string) Str::of((string) $this->route_pattern)->before(',')->before('*')->trim()->trim('/');

        return $path !== '' ? $path : trim((string) (Filament::getCurrentPanel()?->getPath() ?? ''), '/');
    }

    /**
     * Whether the route pattern names a single page. Several patterns, or a
     * wildcard in the middle of the path, need a real URL from the author.
     */
    public function hasSinglePageRoute(): bool
    {
        $pattern = trim((string) $this->route_pattern);

        return ! str_contains($pattern, ',') && ! str_contains(rtrim($pattern, '*'), '*');
    }

    /**
     * Turns a path or a full URL of this app into a path relative to the app
     * root. Returns null for other hosts and for paths with wildcards.
     */
    public static function normalisePagePath(string $input): ?string
    {
        $input = trim($input);
        $root = rtrim(url('/'), '/');

        if (Str::startsWith($input, $root . '/') || $input === $root) {
            $input = Str::after($input, $root);
        } elseif (preg_match('#^[a-z][a-z0-9+.\-]*:|^//#i', $input)) {
            return null;
        }

        if (str_contains($input, '*')) {
            return null;
        }

        return trim($input, '/');
    }

    protected static function pageUrlWith(string $path, string $parameter, string $value): string
    {
        $url = url($path);

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([$parameter => $value]);
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
            ->where(fn (Builder $query) => $query->whereNotNull('completed_at')->orWhereNotNull('dismissed_at'))
            ->exists();
    }

    public function isHint(): bool
    {
        return $this->mode === TourMode::Hint;
    }

    /**
     * Hint mode: the current user's completion row for this version, holding
     * the already-dismissed hint ids.
     */
    public function completionFor(string|int $userId, ?string $tenantId = null): ?TourCompletion
    {
        return $this->completions()
            ->forUser($userId, $tenantId)
            ->where('seen_version', $this->version)
            ->first();
    }
}
