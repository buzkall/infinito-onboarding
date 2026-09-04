<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Enums\TourMode;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Decides which tour (if any) should be shown to a user on a given page.
 *
 * Rule (all must hold, first match by `sort` wins):
 *  1. is_active
 *  2. published_at not null and not in the future
 *  3. now() within [starts_at, ends_at] (null bounds are open)
 *  4. route_pattern matches the current route (`*` wildcards via Str::is)
 *  5. user passes the audience gate
 *  6. tenant_id equals the current tenant, or is null
 *  7. no completion row for the tour's current version
 */
class TourResolver
{
    public function resolveFor(
        Authenticatable $user,
        string $currentRoute,
        ?string $tenantId = null,
        ?TourMode $mode = null,
    ): ?Tour {
        return $this->candidatesFor($user, $currentRoute, $tenantId, $mode)->first();
    }

    /**
     * Every tour the user is eligible for on this route, ordered by `sort`.
     *
     * @return Collection<int, Tour>
     */
    public function candidatesFor(
        Authenticatable $user,
        string $currentRoute,
        ?string $tenantId = null,
        ?TourMode $mode = null,
    ): Collection {
        return $this->eligibleQuery($currentRoute, $tenantId, $mode)
            ->get()
            ->filter(fn (Tour $tour): bool => $this->passes($tour, $user, $currentRoute, $tenantId))
            ->values();
    }

    /**
     * Unseen tours of a mode for a user, regardless of route. Used for the
     * "what's new" badge, where the changelog can be opened from any page.
     *
     * @return Collection<int, Tour>
     */
    public function unseenFor(Authenticatable $user, ?string $tenantId = null, ?TourMode $mode = null): Collection
    {
        return $this->eligibleQuery(null, $tenantId, $mode)
            ->get()
            ->filter(fn (Tour $tour): bool => $this->passesAudience($tour, $user) && ! $this->isSeen($tour, $user, $tenantId))
            ->values();
    }

    /**
     * Whether a specific tour would be shown to the user right now.
     */
    public function passes(Tour $tour, Authenticatable $user, string $currentRoute, ?string $tenantId = null): bool
    {
        return $tour->is_active
            && $tour->isPublished()
            && $tour->isWithinWindow()
            && $tour->matchesRoute($currentRoute)
            && $this->passesTenant($tour, $tenantId)
            && $this->passesAudience($tour, $user)
            && ! $this->isSeen($tour, $user, $tenantId);
    }

    public function isSeen(Tour $tour, Authenticatable $user, ?string $tenantId = null): bool
    {
        return $tour->isSeenBy($user->getAuthIdentifier(), $tenantId);
    }

    public function passesTenant(Tour $tour, ?string $tenantId): bool
    {
        if (blank($tour->tenant_id)) {
            return true;
        }

        return filled($tenantId) && (string) $tour->tenant_id === (string) $tenantId;
    }

    /**
     * Audience gate. The `audience` JSON column may contain any of:
     *
     *  - `roles`:       any of these role names (spatie/laravel-permission's
     *                   hasAnyRole()/hasRole(), or a `roles` attribute / relation)
     *  - `permissions`: any of these permissions / abilities, checked through
     *                   the Gate so any permission package (or plain policies) work
     *  - `users`:       any of these user identifiers
     *
     * Every present criterion must be satisfied. An empty audience means everyone.
     */
    public function passesAudience(Tour $tour, Authenticatable $user): bool
    {
        $audience = $tour->audience ?? [];

        $roles = $this->normaliseList(Arr::get($audience, 'roles'));
        $permissions = $this->normaliseList(Arr::get($audience, 'permissions'));
        $users = $this->normaliseList(Arr::get($audience, 'users'));

        if ($roles !== [] && ! $this->userHasAnyRole($user, $roles)) {
            return false;
        }

        if ($permissions !== [] && ! $this->userHasAnyPermission($user, $permissions)) {
            return false;
        }

        if ($users !== [] && ! in_array((string) $user->getAuthIdentifier(), array_map('strval', $users), true)) {
            return false;
        }

        return true;
    }

    /**
     * @return Builder<Tour>
     */
    protected function eligibleQuery(?string $currentRoute, ?string $tenantId, ?TourMode $mode)
    {
        $query = Tour::query()
            ->with('steps')
            ->active()
            ->published()
            ->withinWindow()
            ->forTenant($tenantId)
            ->ordered();

        if ($currentRoute !== null) {
            $query->forRoute($currentRoute);
        }

        if ($mode !== null) {
            $query->mode($mode);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function userHasAnyRole(Authenticatable $user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($roles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        $userRoles = $this->extractRoleNames($user);

        return $userRoles !== [] && array_intersect($roles, $userRoles) !== [];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function userHasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (Gate::forUser($user)->check($permission)) {
                return true;
            }

            if (method_exists($user, 'hasPermissionTo')) {
                try {
                    if ($user->hasPermissionTo($permission)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Unknown permission names throw in some packages; treat as "no".
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function extractRoleNames(Authenticatable $user): array
    {
        $roles = null;

        if (method_exists($user, 'getAttribute')) {
            $roles = $user->getAttribute('roles');
        } elseif (isset($user->roles)) {
            $roles = $user->roles;
        }

        if ($roles === null) {
            return [];
        }

        return collect($roles)
            ->map(function (mixed $role): ?string {
                if (is_string($role) || is_int($role)) {
                    return (string) $role;
                }

                if (is_array($role)) {
                    return isset($role['name']) ? (string) $role['name'] : null;
                }

                if (is_object($role) && isset($role->name)) {
                    return (string) $role->name;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function normaliseList(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return collect(Arr::wrap($value))
            ->map(fn (mixed $item) => is_scalar($item) ? trim((string) $item) : null)
            ->filter(fn (?string $item) => filled($item))
            ->values()
            ->all();
    }
}
