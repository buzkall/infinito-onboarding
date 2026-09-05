<x-filament-widgets::widget class="io-analytics" data-tour-analytics>
    <x-filament::section
        :heading="__('infinito-onboarding::onboarding.analytics.heading')"
        :description="$summary && $summary['version'] ? __('infinito-onboarding::onboarding.analytics.version', ['version' => $summary['version']]) : __('infinito-onboarding::onboarding.analytics.all_versions')"
        collapsible
    >
        <x-slot name="headerEnd">
            <x-filament::link tag="button" size="sm" color="gray" wire:click="toggleVersions">
                {{ $allVersions ? __('infinito-onboarding::onboarding.analytics.show_current') : __('infinito-onboarding::onboarding.analytics.show_all') }}
            </x-filament::link>
        </x-slot>

        @if (! $summary)
            <p class="fi-ta-empty-state-description">{{ __('infinito-onboarding::onboarding.analytics.empty') }}</p>
        @else
            <div class="io-analytics-stats">
                @foreach ([
                    'views' => $summary['views'],
                    'unique_viewers' => $summary['unique_viewers'],
                    'completed' => $summary['completed'],
                    'dismissed' => $summary['dismissed'],
                    'completion_rate' => $summary['completion_rate'] === null ? '—' : $summary['completion_rate'] . '%',
                ] as $key => $value)
                    <div class="io-analytics-stat" data-stat="{{ $key }}">
                        <div class="io-analytics-stat-value">{{ $value }}</div>
                        <div class="io-analytics-stat-label">{{ __('infinito-onboarding::onboarding.analytics.stats.' . $key) }}</div>
                    </div>
                @endforeach
            </div>

            @if ($summary['steps'] !== [])
                <h4 class="io-analytics-subheading">{{ __('infinito-onboarding::onboarding.analytics.steps') }}</h4>
                <table class="io-analytics-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('infinito-onboarding::onboarding.analytics.columns.step') }}</th>
                            <th class="io-num">{{ __('infinito-onboarding::onboarding.analytics.columns.reached') }}</th>
                            <th class="io-num">{{ __('infinito-onboarding::onboarding.analytics.columns.reached_rate') }}</th>
                            <th class="io-num">{{ __('infinito-onboarding::onboarding.analytics.columns.drop_off') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['steps'] as $step)
                            <tr data-step-id="{{ $step['id'] }}">
                                <td>{{ $step['order'] }}</td>
                                <td>{{ $step['title'] }}</td>
                                <td class="io-num">{{ $step['reached'] }}</td>
                                <td class="io-num">
                                    <span class="io-analytics-bar" style="--io-pct: {{ $step['reached_rate'] ?? 0 }}%"></span>
                                    {{ $step['reached_rate'] === null ? '—' : $step['reached_rate'] . '%' }}
                                </td>
                                <td class="io-num">{{ $step['drop_off'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($summary['missing_targets'] !== [])
                <h4 class="io-analytics-subheading io-analytics-warning">{{ __('infinito-onboarding::onboarding.analytics.missing_targets') }}</h4>
                <table class="io-analytics-table">
                    <thead>
                        <tr>
                            <th>{{ __('infinito-onboarding::onboarding.analytics.columns.selector') }}</th>
                            <th>{{ __('infinito-onboarding::onboarding.analytics.columns.step') }}</th>
                            <th class="io-num">{{ __('infinito-onboarding::onboarding.analytics.columns.count') }}</th>
                            <th>{{ __('infinito-onboarding::onboarding.analytics.columns.last_seen') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['missing_targets'] as $missing)
                            <tr data-missing-selector="{{ $missing['selector'] }}">
                                <td><code>{{ $missing['selector'] }}</code></td>
                                <td>{{ $missing['step_title'] ?? '—' }}</td>
                                <td class="io-num">{{ $missing['count'] }}</td>
                                <td>{{ $missing['last_seen_at'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="io-analytics-hint">{{ __('infinito-onboarding::onboarding.analytics.missing_targets_hint') }}</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
