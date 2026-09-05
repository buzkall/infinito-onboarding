<div class="io-overlay" data-tour-overlay>
    @if ($tour && $payload)
        @if ($tour->isHint())
            <div
                wire:key="io-hints-{{ $tour->id }}-{{ $tour->version }}"
                wire:ignore
                data-tour-key="{{ $tour->key }}"
                data-tour-hints
                x-data="infinitoOnboardingHints({
                    tour: @js($payload['tour']),
                    steps: @js($payload['steps']),
                    dismissed: @js($payload['dismissed_steps'] ?? []),
                    labels: @js($labels),
                    onDismissHint: (stepId) => $wire.dismissHint(stepId),
                    onCompleted: () => $wire.markCompleted(),
                    onEvent: (name, detail) => $wire.track(name, detail),
                })"
            ></div>
        @elseif ($tour->isChangelog())
            <div x-data x-init="$wire.track('view')">
                <x-infinito-onboarding::changelog-modal
                    :id="'io-changelog-' . $tour->id"
                    :tours="[$tour]"
                    :heading="$tour->translated('name')"
                    :auto-open="true"
                />
            </div>
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
                    onEvent: (name, detail) => $wire.track(name, detail),
                })"
            ></div>
        @endif
    @endif
</div>
