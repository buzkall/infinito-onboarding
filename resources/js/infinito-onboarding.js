import { driver } from 'driver.js'

const EVENT_PREFIX = 'infinito-onboarding'
const RETRY_DELAYS = [100, 200, 400, 800] // 5 attempts: immediate + these four waits

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * Turn a step payload into the selector we should query.
 * `[data-tour="…"]` is always preferred; a raw CSS selector is used for
 * `css` targets; `none` steps are centred and need no element.
 */
export function selectorFor(step) {
    if (!step || step.target_type === 'none') return null

    if (step.selector) return step.selector

    if (step.target_type === 'data_tour' && step.target) {
        return `[data-tour="${String(step.target).replace(/"/g, '\\"')}"]`
    }

    return step.target || null
}

export function queryTarget(selector) {
    if (!selector) return null

    try {
        return document.querySelector(selector)
    } catch (error) {
        console.warn(`[${EVENT_PREFIX}] invalid selector "${selector}":`, error?.message ?? error)

        return null
    }
}

/**
 * Wait for an element, retrying with backoff (5 attempts, 100 ms → 800 ms).
 */
export async function waitForTarget(selector, delays = RETRY_DELAYS) {
    let element = queryTarget(selector)

    for (const delay of delays) {
        if (element) return element

        await sleep(delay)
        element = queryTarget(selector)
    }

    return element
}

const PLACEMENTS = {
    top: { side: 'top', align: 'center' },
    right: { side: 'right', align: 'center' },
    bottom: { side: 'bottom', align: 'center' },
    left: { side: 'left', align: 'center' },
}

export function placementFor(step) {
    return PLACEMENTS[step?.placement] ?? {}
}

export function dispatch(name, detail = {}) {
    window.dispatchEvent(new CustomEvent(`${EVENT_PREFIX}:${name}`, { detail, bubbles: true }))
}

/**
 * Alpine component driving a tour with Driver.js.
 *
 * Usage (Blade):
 *   <div x-data="infinitoOnboardingTour({ tour, steps, labels, onCompleted, onDismissed })"></div>
 */
export function infinitoOnboardingTour(config = {}) {
    // Kept outside Alpine's reactive data on purpose: Driver.js mutates its
    // own state constantly and does not need to be observed.
    let instance = null
    let teardown = []

    return {
        tour: config.tour ?? null,
        steps: Array.isArray(config.steps) ? config.steps : [],
        labels: {
            next: 'Next',
            previous: 'Back',
            done: 'Done',
            progress: '{{current}} of {{total}}',
            ...(config.labels ?? {}),
        },
        options: config.options ?? {},
        autoStart: config.autoStart ?? true,
        running: false,

        init() {
            if (this.autoStart) {
                this.$nextTick(() => this.start())
            }

            this.$el.addEventListener(`${EVENT_PREFIX}:start`, () => this.start())
        },

        destroy() {
            this.stop()
        },

        isActive() {
            return Boolean(instance?.isActive())
        },

        async start() {
            if (this.running || this.steps.length === 0) return

            this.running = true

            const resolved = await this.resolveSteps()

            if (resolved.length === 0) {
                console.warn(`[${EVENT_PREFIX}] tour "${this.tour?.key ?? '?'}" has no reachable steps; nothing to show.`)
                this.running = false
                this.finish('dismissed')

                return
            }

            instance = driver({
                showProgress: resolved.length > 1,
                progressText: this.labels.progress,
                nextBtnText: this.labels.next,
                prevBtnText: this.labels.previous,
                doneBtnText: this.labels.done,
                popoverClass: 'io-popover',
                stagePadding: 6,
                stageRadius: 8,
                allowClose: true,
                smoothScroll: true,
                skipMissingElement: true,
                ...this.options,
                steps: resolved.map((entry) => this.toDriverStep(entry)),
                onHighlightStarted: (element, step, opts) => {
                    // onHighlightStarted (not onHighlighted) so fast clicks that
                    // interrupt Driver.js' animation still emit one event per step.
                    dispatch('step', {
                        tour: this.tour,
                        step: step?.data?.payload ?? null,
                        index: opts?.index ?? null,
                        element,
                    })
                },
                // Driver.js hands control to us for every exit path. We destroy
                // and finish ourselves instead of relying on onDestroyed, which
                // Driver.js skips when the exit happens mid-animation.
                onDoneClick: () => this.end('completed'),
                onCloseClick: () => this.end('dismissed'),
                onDestroyStarted: () => this.end(instance?.isLastStep() ? 'completed' : 'dismissed'),
                onDestroyed: () => {
                    if (instance) this.end('dismissed')
                },
            })

            this.bindLivewire()
            instance.drive()
        },

        stop() {
            this.end('dismissed')
        },

        end(outcome) {
            const current = instance

            instance = null
            this.unbindLivewire()

            if (!current) {
                this.running = false

                return
            }

            try {
                current.destroy()
            } catch (error) {
                console.warn(`[${EVENT_PREFIX}] could not tear down the tour cleanly:`, error)
            }

            this.running = false
            this.finish(outcome)
        },

        finish(outcome) {
            const detail = { tour: this.tour, outcome }

            dispatch(outcome, detail)

            const callback = outcome === 'completed' ? config.onCompleted : config.onDismissed

            if (typeof callback === 'function') {
                callback(detail)
            }
        },

        async resolveSteps() {
            const resolved = []

            for (const step of this.steps) {
                const selector = selectorFor(step)

                if (!selector) {
                    resolved.push({ step, selector: null })

                    continue
                }

                const element = await waitForTarget(selector)

                if (!element) {
                    console.warn(
                        `[${EVENT_PREFIX}] target not found for step "${step.title ?? step.id ?? '?'}" (selector: ${selector}); skipping.`,
                    )

                    continue
                }

                resolved.push({ step, selector })
            }

            return resolved
        },

        toDriverStep({ step, selector }) {
            const driverStep = {
                popover: {
                    title: step.title ?? '',
                    description: step.body ?? '',
                    ...placementFor(step),
                },
                data: { payload: step },
            }

            if (selector) {
                // A function so the element is re-queried after Livewire morphs.
                driverStep.element = () => queryTarget(selector) ?? undefined
            }

            return driverStep
        },

        refresh() {
            if (!instance?.isActive()) return

            window.requestAnimationFrame(() => instance?.refresh())
        },

        bindLivewire() {
            const refresh = () => this.refresh()
            const stop = () => this.stop()

            document.addEventListener('livewire:navigated', refresh)
            document.addEventListener('livewire:navigate', stop)
            window.addEventListener('resize', refresh)

            teardown.push(() => document.removeEventListener('livewire:navigated', refresh))
            teardown.push(() => document.removeEventListener('livewire:navigate', stop))
            teardown.push(() => window.removeEventListener('resize', refresh))

            if (window.Livewire?.hook) {
                let scheduled = null

                const onMorph = () => {
                    if (scheduled) return

                    scheduled = window.setTimeout(() => {
                        scheduled = null
                        refresh()
                    }, 50)
                }

                for (const hook of ['morph.updated', 'morph.added', 'morph.removed', 'morphed', 'commit.after']) {
                    try {
                        window.Livewire.hook(hook, onMorph)
                    } catch (_) {
                        // Hook not available in this Livewire version.
                    }
                }
            }
        },

        unbindLivewire() {
            while (teardown.length) {
                teardown.pop()()
            }
        },
    }
}

function register(Alpine) {
    Alpine.data('infinitoOnboardingTour', infinitoOnboardingTour)
}

if (window.Alpine) {
    register(window.Alpine)
} else {
    document.addEventListener('alpine:init', () => register(window.Alpine))
}

window.InfinitoOnboarding = Object.assign(window.InfinitoOnboarding ?? {}, {
    tour: infinitoOnboardingTour,
    selectorFor,
    queryTarget,
    waitForTarget,
    driver,
})
