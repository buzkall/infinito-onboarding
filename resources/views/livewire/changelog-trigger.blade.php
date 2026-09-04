@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div class="io-changelog-trigger" data-changelog-trigger @if ($hasUnseen) data-unseen @endif>
    @if ($changelogs->isNotEmpty())
        <x-filament::icon-button
            :badge="$hasUnseen ? '•' : null"
            badge-color="primary"
            color="gray"
            :icon="Heroicon::OutlinedSparkles"
            icon-size="lg"
            :label="__('infinito-onboarding::onboarding.changelog.trigger')"
            x-on:click="$dispatch('open-modal', { id: 'io-changelog-trigger' })"
            class="io-changelog-trigger-btn"
        />

        <x-infinito-onboarding::changelog-modal
            id="io-changelog-trigger"
            :tours="$changelogs"
            :auto-open="false"
        />
    @endif
</div>
