<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Models\TourCompletion;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Illuminate\Support\Facades\DB;

/**
 * Personal data the package stores per user: seen-state and analytics
 * events. User ids are plain strings without a foreign key, so deleting a
 * user does not cascade; call forget() from your user deletion flow.
 */
class UserData
{
    /**
     * Delete every completion and event of the user, across all tenants.
     *
     * @return array{completions: int, events: int}
     */
    public function forget(string|int $userId): array
    {
        return DB::transaction(fn (): array => [
            'completions' => TourCompletion::query()->where('user_id', (string) $userId)->delete(),
            'events' => TourEvent::query()->where('user_id', (string) $userId)->delete(),
        ]);
    }
}
