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

/*
|--------------------------------------------------------------------------
| Livewire request tracking
|--------------------------------------------------------------------------
|
| Preconditions and advance-on-click steps often trigger a Livewire request
| (open a modal, switch a tab). We count in-flight commits so the runner can
| wait for the DOM to settle before looking for the next target.
*/
let inflightCommits = 0
let commitHookBound = false

function bindCommitTracking() {
    if (commitHookBound || !window.Livewire?.hook) return

    commitHookBound = true

    try {
        window.Livewire.hook('commit', ({ succeed, fail }) => {
            inflightCommits++

            const done = () => {
                inflightCommits = Math.max(0, inflightCommits - 1)
            }

            succeed(done)
            fail(done)
        })
    } catch (_) {
        commitHookBound = false
    }
}

export async function waitForLivewireIdle(timeout = 3000) {
    bindCommitTracking()

    // Give a click a moment to start its request before checking.
    await sleep(30)

    const startedAt = Date.now()

    while (inflightCommits > 0 && Date.now() - startedAt < timeout) {
        await sleep(50)
    }

    // One more frame so morphs triggered by the response are applied.
    await new Promise((resolve) => window.requestAnimationFrame(() => resolve()))
}

/**
 * Selector for a precondition action: either an explicit selector, or a
 * (target_type, target) pair like a step.
 */
export function actionSelector(action) {
    if (!action) return null

    if (action.selector) return action.selector

    if (action.target_type || action.target) {
        return selectorFor({ target_type: action.target_type ?? 'data_tour', target: action.target })
    }

    return null
}

const delaysFor = (timeout) => {
    const delays = []
    let total = 0
    let next = 100

    while (total < timeout) {
        delays.push(next)
        total += next
        next = Math.min(next * 2, 800)
    }

    return delays.length ? delays : [100]
}

/**
 * Run a step's `before` actions (open a modal, switch a tab, wait for an
 * element) sequentially. Never throws; a failed action is logged and skipped.
 */
export async function runPreconditions(step) {
    const actions = Array.isArray(step?.before) ? step.before : []

    for (const action of actions) {
        const selector = actionSelector(action)
        const timeout = Number(action.timeout) > 0 ? Number(action.timeout) : 2000

        if (action.type === 'click' || action.type === undefined) {
            const element = selector ? await waitForTarget(selector) : null

            if (!element) {
                console.warn(`[${EVENT_PREFIX}] precondition click target not found (selector: ${selector}); continuing.`)

                continue
            }

            element.click()
            await waitForLivewireIdle(timeout)

            continue
        }

        if (action.type === 'wait') {
            if (!selector) {
                await sleep(Math.min(timeout, 5000))

                continue
            }

            const element = await waitForTarget(selector, delaysFor(timeout))

            if (!element) {
                console.warn(`[${EVENT_PREFIX}] precondition wait target never appeared (selector: ${selector}); continuing.`)
            }

            continue
        }

        if (action.type === 'dispatch' && action.event) {
            window.dispatchEvent(new CustomEvent(action.event, { detail: action.detail ?? {} }))
            await waitForLivewireIdle(timeout)
        }
    }
}

const hasPreconditions = (step) => Array.isArray(step?.before) && step.before.length > 0

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

    const report = (name, detail = {}) => {
        if (typeof config.onEvent !== 'function') return

        try {
            config.onEvent(name, detail)
        } catch (error) {
            console.warn(`[${EVENT_PREFIX}] onEvent callback failed:`, error)
        }
    }

    const missing = (step, selector) => {
        console.warn(`[${EVENT_PREFIX}] target not found for step "${step.title ?? step.id ?? '?'}" (selector: ${selector}); skipping.`)
        report('target_missing', { step_id: step.id ?? null, step_title: step.title ?? null, selector, target_type: step.target_type ?? null, target: step.target ?? null })
    }

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

            if (hasPreconditions(step)) {
                // The target may only exist after the preconditions ran
                // (inside a modal, a tab…); it is checked right before the
                // step is shown instead.
                resolved.push({ step, selector })

                continue
            }

            const element = await waitForTarget(selector)

            if (!element) {
                missing(step, selector)

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

        if (step.advance_on_click) {
            // Clicking the highlighted element (e.g. a wire:click button)
            // advances the tour once Livewire has settled.
            driverStep.advanceOnClick = true
            driverStep.disableActiveInteraction = false
        }

        return driverStep
    }

    /**
     * Find the next reachable step in a direction, running preconditions on
     * the way, and tell Driver.js to move there. Steps whose target is still
     * missing after their preconditions are skipped with a warning.
     */
    let navigating = false

    const navigate = async (direction, resolved) => {
        if (!instance || navigating) return

        navigating = true

        try {
            const active = instance.getActiveIndex()

            if (active === undefined) return

            let index = active + direction

            while (index >= 0 && index < resolved.length) {
                const { step, selector } = resolved[index]

                await runPreconditions(step)

                if (!instance) return

                if (!selector || (await waitForTarget(selector))) {
                    instance.moveTo(index)

                    return
                }

                missing(step, selector)

                index += direction
            }

            if (direction > 0) {
                end('completed')
            }
        } finally {
            navigating = false
        }
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

        // Preconditions of the first step run before the overlay appears.
        let firstIndex = 0

        while (firstIndex < resolved.length) {
            const { step, selector } = resolved[firstIndex]

            await runPreconditions(step)

            if (!selector || (await waitForTarget(selector))) break

            missing(step, selector)
            firstIndex++
        }

        if (firstIndex >= resolved.length) {
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
                const payload = step?.data?.payload ?? null

                dispatch('step', {
                    tour,
                    step: payload,
                    index: opts?.index ?? null,
                    element,
                })

                report('step', { step_id: payload?.id ?? null, index: opts?.index ?? null })
            },
            // Driver.js hands control to us for every exit path. We destroy
            // and finish ourselves instead of relying on onDestroyed, which
            // Driver.js skips when the exit happens mid-animation.
            onDoneClick: () => end('completed'),
            onCloseClick: () => end('dismissed'),
            onNextClick: async (element, step) => {
                if (step?.advanceOnClick) {
                    await waitForLivewireIdle()
                }

                await navigate(1, resolved)
            },
            onPrevClick: () => navigate(-1, resolved),
            onDestroyStarted: () => end(instance?.isLastStep() ? 'completed' : 'dismissed'),
            onDestroyed: () => {
                if (instance) end('dismissed')
            },
        })

        bind()
        report('view', { index: firstIndex })
        instance.drive(firstIndex)
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

/**
 * Hint mode: a pulsing beacon next to every step's target. Clicking a
 * beacon highlights the element with the step's popover and a "Got it"
 * button; dismissed hints disappear, and once all are gone the tour is
 * completed.
 *
 *   <div x-data="infinitoOnboardingHints({ tour, steps, dismissed, labels, onDismissHint, onCompleted, onEvent })"></div>
 */
export function infinitoOnboardingHints(config = {}) {
    const tour = config.tour ?? null
    const labels = { got_it: 'Got it', open_hint: 'Open hint: :title', ...(config.labels ?? {}) }
    const dismissed = new Set((Array.isArray(config.dismissed) ? config.dismissed : []).map(Number))

    let beacons = []
    let instance = null
    let teardown = []
    let frame = null
    let viewed = false

    const report = (name, detail = {}) => {
        if (typeof config.onEvent !== 'function') return

        try {
            config.onEvent(name, detail)
        } catch (error) {
            console.warn(`[${EVENT_PREFIX}] onEvent callback failed:`, error)
        }
    }

    const position = (beacon) => {
        const element = queryTarget(beacon.selector)

        if (!element || !element.isConnected) {
            beacon.node.hidden = true

            return
        }

        const rect = element.getBoundingClientRect()

        if (rect.width === 0 && rect.height === 0) {
            beacon.node.hidden = true

            return
        }

        beacon.node.hidden = false
        beacon.node.style.top = `${rect.top + window.scrollY - 6}px`
        beacon.node.style.left = `${rect.right + window.scrollX - 6}px`
    }

    const reposition = () => {
        if (frame) return

        frame = window.requestAnimationFrame(() => {
            frame = null
            beacons.forEach(position)
        })
    }

    const remove = (beacon) => {
        beacon.node.remove()
        beacons = beacons.filter((candidate) => candidate !== beacon)
    }

    const closePopover = () => {
        const current = instance

        instance = null

        try {
            current?.destroy()
        } catch (_) {
            // Already gone.
        }
    }

    const dismiss = (beacon) => {
        closePopover()
        dismissed.add(Number(beacon.step.id))
        remove(beacon)

        if (typeof config.onDismissHint === 'function' && beacon.step.id != null) {
            config.onDismissHint(beacon.step.id)
        }

        dispatch('hint-dismissed', { tour, step: beacon.step })

        if (beacons.length === 0) {
            dispatch('completed', { tour, outcome: 'completed' })

            if (typeof config.onCompleted === 'function' && beacon.step.id == null) {
                config.onCompleted({ tour, outcome: 'completed' })
            }
        }
    }

    const open = (beacon) => {
        const element = queryTarget(beacon.selector)

        if (!element) {
            reposition()

            return
        }

        closePopover()

        instance = driver({
            popoverClass: 'io-popover io-hint-popover',
            stagePadding: 6,
            stageRadius: 8,
            allowClose: true,
            animate: true,
            smoothScroll: true,
            showButtons: [],
            onDestroyStarted: () => closePopover(),
            onPopoverRender: (popover) => {
                const button = document.createElement('button')
                button.type = 'button'
                // Not a driver-popover-*-btn class: Driver.js captures clicks on
                // those and would swallow ours.
                button.className = 'io-hint-got-it'
                button.textContent = labels.got_it
                button.addEventListener('click', () => dismiss(beacon))

                popover.footer.style.display = 'flex'
                popover.footerButtons.innerHTML = ''
                popover.footerButtons.appendChild(button)
            },
        })

        instance.highlight({
            element,
            popover: {
                title: beacon.step.title ?? '',
                description: beacon.step.body ?? '',
                ...placementFor(beacon.step),
            },
        })

        dispatch('step', { tour, step: beacon.step, element })
        report('step', { step_id: beacon.step.id ?? null })
    }

    const steps = Array.isArray(config.steps) ? config.steps : []

    const mountOne = async (step) => {
        if (dismissed.has(Number(step.id))) return

        const selector = selectorFor(step)

        if (!selector) return

        const element = await waitForTarget(selector)

        if (!element) {
            console.warn(`[${EVENT_PREFIX}] hint target not found for "${step.title ?? step.id ?? '?'}" (selector: ${selector}); skipping.`)
            report('target_missing', { step_id: step.id ?? null, step_title: step.title ?? null, selector })

            return
        }

        const node = document.createElement('button')
        node.type = 'button'
        node.className = 'io-beacon'
        node.setAttribute('data-io-beacon', String(step.id ?? ''))
        node.setAttribute('aria-label', labels.open_hint.replace(':title', step.title ?? ''))
        node.innerHTML = '<span class="io-beacon-pulse"></span><span class="io-beacon-dot"></span>'

        const beacon = { step, selector, node }

        node.addEventListener('click', (event) => {
            event.preventDefault()
            event.stopPropagation()
            open(beacon)
        })

        document.body.appendChild(node)
        beacons.push(beacon)
        position(beacon)

        if (!viewed) {
            // Reported on the first beacon, not after every target resolved:
            // a fast user may dismiss everything before a missing target
            // times out.
            viewed = true
            report('view', { hints: steps.length })
        }
    }

    // Beacons mount concurrently so a missing target never delays the others.
    const mount = async (steps) => {
        await Promise.all(steps.map(mountOne))
    }

    const bind = () => {
        window.addEventListener('scroll', reposition, true)
        window.addEventListener('resize', reposition)
        document.addEventListener('livewire:navigated', reposition)

        teardown.push(() => window.removeEventListener('scroll', reposition, true))
        teardown.push(() => window.removeEventListener('resize', reposition))
        teardown.push(() => document.removeEventListener('livewire:navigated', reposition))

        if (window.Livewire?.hook) {
            for (const hook of ['morph.updated', 'morph.added', 'morph.removed', 'morphed']) {
                try {
                    window.Livewire.hook(hook, reposition)
                } catch (_) {
                    // Hook not available.
                }
            }
        }

        const interval = window.setInterval(reposition, 1000)
        teardown.push(() => window.clearInterval(interval))
    }

    return {
        init() {
            bind()
            this.$nextTick(() => mount(steps))
        },

        destroy() {
            closePopover()
            beacons.forEach((beacon) => beacon.node.remove())
            beacons = []

            while (teardown.length) teardown.pop()()
        },

        dismissAll() {
            closePopover()
            beacons.forEach((beacon) => beacon.node.remove())
            beacons = []

            if (typeof config.onCompleted === 'function') {
                config.onCompleted({ tour, outcome: 'completed' })
            }

            dispatch('completed', { tour, outcome: 'completed' })
        },

        count() {
            return beacons.length
        },
    }
}

function register(Alpine) {
    Alpine.data('infinitoOnboardingTour', infinitoOnboardingTour)
    Alpine.data('infinitoOnboardingHints', infinitoOnboardingHints)
}

if (window.Alpine) {
    register(window.Alpine)
} else {
    document.addEventListener('alpine:init', () => register(window.Alpine))
}

window.InfinitoOnboarding = Object.assign(window.InfinitoOnboarding ?? {}, {
    tour: infinitoOnboardingTour,
    hints: infinitoOnboardingHints,
    createTourRunner,
    runPreconditions,
    waitForLivewireIdle,
    selectorFor,
    queryTarget,
    waitForTarget,
    driver,
})
