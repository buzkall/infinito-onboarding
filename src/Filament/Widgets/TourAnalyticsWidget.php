<?php

namespace Arzcode\InfinitoOnboarding\Filament\Widgets;

use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Support\TourAnalytics;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * Views, completions, per-step drop-off and missing targets for a tour.
 * Rendered on the tour's edit page.
 */
class TourAnalyticsWidget extends Widget
{
    public ?Tour $record = null;

    public bool $allVersions = false;

    /** Rendered inline so the numbers are in the page on first paint. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return TourAnalytics::isEnabled();
    }

    public function toggleVersions(): void
    {
        $this->allVersions = ! $this->allVersions;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'infinito-onboarding::widgets.tour-analytics';

        return view($view, $this->getViewData());
    }

    protected function getViewData(): array
    {
        if ($this->record === null) {
            return ['summary' => null];
        }

        return [
            'summary' => app(TourAnalytics::class)->summary($this->record, allVersions: $this->allVersions),
        ];
    }
}
