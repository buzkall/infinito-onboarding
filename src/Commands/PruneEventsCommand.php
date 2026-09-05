<?php

namespace Arzcode\InfinitoOnboarding\Commands;

use Arzcode\InfinitoOnboarding\Support\TourAnalytics;
use Illuminate\Console\Command;

class PruneEventsCommand extends Command
{
    protected $signature = 'onboarding:prune-events
        {--days= : Delete events older than this many days (defaults to config infinito-onboarding.analytics.prune_after_days)}';

    protected $description = 'Delete old onboarding analytics events';

    public function handle(TourAnalytics $analytics): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        $deleted = $analytics->prune($days);

        $this->components->info("Deleted {$deleted} event(s).");

        return self::SUCCESS;
    }
}
