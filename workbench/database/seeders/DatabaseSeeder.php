<?php

namespace Workbench\Database\Seeders;

use Arzcode\InfinitoOnboarding\Enums\TourEventType;
use Arzcode\InfinitoOnboarding\Models\Tour;
use Arzcode\InfinitoOnboarding\Models\TourEvent;
use Arzcode\InfinitoOnboarding\Tests\Fixtures\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(['email' => 'alicia@example.com'], [
            'name' => 'Alicia',
            'password' => bcrypt('password'),
            'roles' => ['admin'],
        ]);

        $tour = Tour::define('orders-q3')
            ->name('What is new in orders')
            ->description('Faster exports and bulk actions.')
            ->route('admin/orders*')
            ->version('2.0')
            ->sort(1)
            ->step('export-orders', 'Export in one click', '<p>Download the current view as CSV or XLSX. Filters and sorting are respected.</p>', 'bottom')
            ->step('status-field', 'Status colours', '<p>The status now follows the payment state automatically.</p>', 'right')
            ->step('priority-field', 'Priority shipping', '<p>Toggle it and the order jumps the fulfilment queue.</p>', 'left')
            ->note('That is all for now', '<p>Bump the tour version in the resource to show it again.</p>')
            ->publish()
            ->save();

        Tour::define('order-hints')
            ->name('New toolbar hints')
            ->hints()
            ->route('admin/orders*')
            ->sort(2)
            ->step('bulk-actions', 'Bulk actions', '<p>Select several orders to ship, tag or export them together.</p>', 'bottom')
            ->step('shipped-field', 'Shipping date', '<p>Now editable inline; carriers are notified automatically.</p>', 'top')
            ->publish()
            ->save();

        Tour::define('release-2-4')
            ->name('Release 2.4')
            ->description('September 2026')
            ->changelog()
            ->route('admin/orders*')
            ->sort(3)
            ->note('Faster exports', '<p>Exports run in the background and land in your inbox.</p>')
            ->note('Bulk actions', '<p>Ship, tag or export several orders at once.</p>')
            ->note('Dark mode polish', '<p>Tables and modals were tuned for dark backgrounds.</p>')
            ->publish()
            ->save();

        Tour::define('welcome-draft')->name('Welcome (draft)')->route('admin')->sort(9)->note('Hello')->save();

        if (TourEvent::count() === 0) {
            foreach (range(1, 42) as $i) {
                TourEvent::query()->create(['tour_id' => $tour->id, 'user_id' => $i, 'version' => '2.0', 'event' => TourEventType::View, 'created_at' => now()->subDays($i % 9)]);
                foreach ($tour->steps as $index => $step) {
                    if ($i % ($index + 1) === 0) {
                        TourEvent::query()->create(['tour_id' => $tour->id, 'step_id' => $step->id, 'user_id' => $i, 'version' => '2.0', 'event' => TourEventType::Step, 'meta' => ['index' => $index], 'created_at' => now()->subDays($i % 9)]);
                    }
                }
                if ($i % 4 === 0) {
                    TourEvent::query()->create(['tour_id' => $tour->id, 'user_id' => $i, 'version' => '2.0', 'event' => TourEventType::Completed, 'created_at' => now()->subDays($i % 9)]);
                } elseif ($i % 5 === 0) {
                    TourEvent::query()->create(['tour_id' => $tour->id, 'user_id' => $i, 'version' => '2.0', 'event' => TourEventType::Dismissed, 'created_at' => now()->subDays($i % 9)]);
                }
            }
            foreach (range(1, 3) as $i) {
                TourEvent::query()->create(['tour_id' => $tour->id, 'user_id' => $i, 'version' => '2.0', 'event' => TourEventType::TargetMissing, 'meta' => ['selector' => '[data-tour="old-export"]', 'step_title' => 'Export in one click'], 'created_at' => now()->subDays($i)]);
            }
        }
    }
}
