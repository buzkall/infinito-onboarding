@props([
    'id',
    'tours',
    'heading' => null,
    'autoOpen' => false,
    'completeAction' => 'markCompleted',
    'dismissAction' => 'markDismissed',
])

<div
    class="io-changelog"
    data-tour-changelog
    x-data="{ completed: false }"
    @if ($autoOpen)
        x-init="$nextTick(() => $dispatch('open-modal', { id: @js($id) }))"
    @endif
    x-on:modal-closed.window="if ($event.detail.id === @js($id) && ! completed) { $wire.{{ $dismissAction }}() }"
>
    <x-filament::modal
        :id="$id"
        width="lg"
        :heading="$heading ?? __('infinito-onboarding::onboarding.changelog.heading')"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        :close-button="true"
    >
        <div class="io-changelog-entries">
            @foreach ($tours as $tour)
                <section class="io-changelog-tour" data-tour-key="{{ $tour->key }}">
                    @if (count($tours) > 1 || filled($tour->translated('description')))
                        <header class="io-changelog-tour-header">
                            @if (count($tours) > 1)
                                <h3 class="io-changelog-tour-name">{{ $tour->translated('name') }}</h3>
                                <span class="io-changelog-tour-version">v{{ $tour->version }}</span>
                            @endif
                            @if (filled($tour->translated('description')))
                                <p class="io-changelog-tour-description">{{ $tour->translated('description') }}</p>
                            @endif
                        </header>
                    @endif

                    <ul class="io-changelog-list">
                        @foreach ($tour->steps as $step)
                            <li class="io-changelog-entry">
                                <h4 class="io-changelog-entry-title">{{ $step->translated('title') }}</h4>
                                @if (filled($step->translated('body')))
                                    <div class="io-changelog-entry-body">{!! $step->translated('body') !!}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="completed = true; $wire.{{ $completeAction }}(); $dispatch('close-modal', { id: @js($id) })"
                data-changelog-complete
            >
                {{ __('infinito-onboarding::onboarding.changelog.got_it') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
