<div class="io-overlay" data-tour-overlay>
    @if ($tour && $payload)
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
</div>
