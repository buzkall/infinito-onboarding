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
 * Framework-agnostic tour runner around Driver.js. Used by the Alpine
 * component below and by record mode's "Preview" button.
 *
 *   const runner = createTourRunner({ tour, steps, labels, options, onCompleted, onDismissed })
 *   await runner.start()
 */
export function createTourRunner(config = {}) {
    const tour = config.tour ?? null
    const steps = Array.isArray(config.steps) ? config.steps : []
    const labels = {
        next: 'Next',
        previous: 'Back',
        done: 'Done',
        progress: '{{current}} of {{total}}',
        ...(config.labels ?? {}),
    }
    const options = config.options ?? {}

    let instance = null
    let running = false
    let teardown = []

    const finish = (outcome) => {
        const detail = { tour, outcome }

        dispatch(outcome, detail)

        const callback = outcome === 'completed' ? config.onCompleted : config.onDismissed

        if (typeof callback === 'function') {
            callback(detail)
        }
    }

    const unbind = () => {
        while (teardown.length) {
            teardown.pop()()
        }
    }

    const refresh = () => {
        if (!instance?.isActive()) return

        window.requestAnimationFrame(() => instance?.refresh())
    }

    const end = (outcome) => {
        const current = instance

        instance = null
        unbind()

        if (!current) {
            running = false

            return
        }

        try {
            current.destroy()
        } catch (error) {
            console.warn(`[${EVENT_PREFIX}] could not tear down the tour cleanly:`, error)
        }

        running = false
        finish(outcome)
    }

    const bind = () => {
        const stop = () => end('dismissed')

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
    }

    const resolveSteps = async () => {
        const resolved = []

        for (const step of steps) {
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
    }

    const toDriverStep = ({ step, selector }) => {
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
    }

    const start = async () => {
        if (running || steps.length === 0) return

        running = true

        const resolved = await resolveSteps()

        if (resolved.length === 0) {
            console.warn(`[${EVENT_PREFIX}] tour "${tour?.key ?? '?'}" has no reachable steps; nothing to show.`)
            running = false
            finish('dismissed')

            return
        }

        instance = driver({
            showProgress: resolved.length > 1,
            progressText: labels.progress,
            nextBtnText: labels.next,
            prevBtnText: labels.previous,
            doneBtnText: labels.done,
            popoverClass: 'io-popover',
            stagePadding: 6,
            stageRadius: 8,
            allowClose: true,
            smoothScroll: true,
            skipMissingElement: true,
            ...options,
            steps: resolved.map(toDriverStep),
            onHighlightStarted: (element, step, opts) => {
                // onHighlightStarted (not onHighlighted) so fast clicks that
                // interrupt Driver.js' animation still emit one event per step.
                dispatch('step', {
                    tour,
                    step: step?.data?.payload ?? null,
                    index: opts?.index ?? null,
                    element,
                })
            },
            // Driver.js hands control to us for every exit path. We destroy
            // and finish ourselves instead of relying on onDestroyed, which
            // Driver.js skips when the exit happens mid-animation.
            onDoneClick: () => end('completed'),
            onCloseClick: () => end('dismissed'),
            onDestroyStarted: () => end(instance?.isLastStep() ? 'completed' : 'dismissed'),
            onDestroyed: () => {
                if (instance) end('dismissed')
            },
        })

        bind()
        instance.drive()
    }

    return {
        start,
        stop: () => end('dismissed'),
        refresh,
        isActive: () => Boolean(instance?.isActive()),
        isRunning: () => running,
    }
}

/**
 * Alpine component driving a tour with Driver.js.
 *
 * Usage (Blade):
 *   <div x-data="infinitoOnboardingTour({ tour, steps, labels, onCompleted, onDismissed })"></div>
 */
export function infinitoOnboardingTour(config = {}) {
    let runner = null

    return {
        autoStart: config.autoStart ?? true,

        init() {
            runner = createTourRunner(config)

            if (this.autoStart) {
                this.$nextTick(() => this.start())
            }

            this.$el.addEventListener(`${EVENT_PREFIX}:start`, () => this.start())
        },

        destroy() {
            this.stop()
        },

        start() {
            return runner?.start()
        },

        stop() {
            runner?.stop()
        },

        refresh() {
            runner?.refresh()
        },

        isActive() {
            return Boolean(runner?.isActive())
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
    createTourRunner,
    selectorFor,
    queryTarget,
    waitForTarget,
    driver,
})
