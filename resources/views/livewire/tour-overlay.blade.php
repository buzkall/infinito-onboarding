<div class="io-overlay" data-tour-overlay>
    @if ($tour && $payload)
        @if ($tour->isChangelog())
            <x-infinito-onboarding::changelog-modal
                :id="'io-changelog-' . $tour->id"
                :tours="[$tour]"
                :heading="$tour->name"
                :auto-open="true"
            />
        @else
            <div
                wire:key="io-tour-{{ $tour->id }}-{{ $tour->version }}"
                wire:ignore
                data-tour-key="{{ $tour->key }}"
                x-data="infinitoOnboardingTour({
                    tour: @js($payload['tour']),
                    steps: @js($payload['steps']),
                    labels: @js($labels),
                    onCompleted: () => $wire.markCompleted(),
                    onDismissed: () => $wire.markDismissed(),
                })"
            ></div>
        @endif
    @endif
</div>
