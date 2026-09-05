<?php

namespace Arzcode\InfinitoOnboarding\Support;

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Arzcode\InfinitoOnboarding\Models\TourStep;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates the events table into the numbers the analytics widget shows.
 */
class TourAnalytics
{
    public static function isEnabled(): bool
    {
        return (bool) config('infinito-onboarding.analytics.enabled', true);
    }

    /**
     * @return array{
     *     version: string|null,
     *     views: int,
     *     unique_viewers: int,
     *     completed: int,
     *     dismissed: int,
     *     completion_rate: float|null,
     *     steps: array<int, array{id: int, order: int, title: string, reached: int, reached_rate: float|null, drop_off: int}>,
     *     missing_targets: array<int, array{selector: string, step_title: string|null, count: int, last_seen_at: string|null}>
     * }
     */
    public function summary(Tour $tour, ?string $version = null, bool $allVersions = false): array
    {
        $version ??= $tour->version;

        $events = fn (): Builder => TourEvent::query()
            ->where('tour_id', $tour->id)
            ->when(! $allVersions, fn (Builder $query) => $query->where('version', $version));

        $views = (int) $events()->ofType(TourEventType::View)->count();
        $uniqueViewers = (int) $events()->ofType(TourEventType::View)->distinct('user_id')->count('user_id');
        $completed = (int) $events()->ofType(TourEventType::Completed)->count();
        $dismissed = (int) $events()->ofType(TourEventType::Dismissed)->count();

        $reachedByStep = $events()
            ->ofType(TourEventType::Step)
            ->whereNotNull('step_id')
            ->selectRaw('step_id, count(*) as aggregate')
            ->groupBy('step_id')
            ->pluck('aggregate', 'step_id')
            ->map(fn ($count): int => (int) $count);

        $steps = $tour->steps->map(fn (TourStep $step): array => [
            'id' => $step->id,
            'order' => $step->order,
            'title' => $step->title,
            'reached' => $reached = (int) ($reachedByStep[$step->id] ?? 0),
            'reached_rate' => $views > 0 ? round($reached / $views * 100, 1) : null,
            'drop_off' => 0,
        ])->values()->all();

        foreach ($steps as $index => $step) {
            $previous = $index === 0 ? $views : $steps[$index - 1]['reached'];
            $steps[$index]['drop_off'] = max(0, $previous - $step['reached']);
        }

        $missing = $events()
            ->ofType(TourEventType::TargetMissing)
            ->get()
            ->groupBy(fn (TourEvent $event): string => (string) ($event->meta['selector'] ?? '?'))
            ->map(fn ($group, string $selector): array => [
                'selector' => $selector,
                'step_title' => $group->first()->meta['step_title'] ?? null,
                'count' => $group->count(),
                'last_seen_at' => $group->max('created_at')?->toDateTimeString(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'version' => $allVersions ? null : $version,
            'views' => $views,
            'unique_viewers' => $uniqueViewers,
            'completed' => $completed,
            'dismissed' => $dismissed,
            'completion_rate' => $views > 0 ? round($completed / $views * 100, 1) : null,
            'steps' => $steps,
            'missing_targets' => $missing,
        ];
    }

    /**
     * Delete events older than the given number of days. Returns the count.
     */
    public function prune(?int $days = null): int
    {
        $days ??= (int) config('infinito-onboarding.analytics.prune_after_days', 90);

        return TourEvent::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
