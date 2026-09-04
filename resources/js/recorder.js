/**
 * Record mode: visual authoring of tour steps.
 *
 * Activated by the TourRecorder Livewire component (query flag
 * `?onboarding-record=<tour-key>`, authorised users only). Hover highlights
 * the element under the cursor with the selector it would capture; clicking
 * captures it using the targeting priority (data-tour → id → wire:key →
 * generated CSS path), scores it, and opens the step form. Steps are kept in
 * the floating panel, can be reordered, previewed and saved through Livewire.
 */

const IGNORE_ATTRIBUTE = 'data-io-recorder'
const UI_SELECTOR = `[${IGNORE_ATTRIBUTE}], .driver-popover, .driver-overlay, #driver-dummy-element`

const cssEscape = (value) => (window.CSS?.escape ? window.CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&'))

const attributeSelector = (attribute, value) => `[${attribute.replace(/:/g, '\\:')}="${String(value).replace(/["\\]/g, '\\$&')}"]`

export function countMatches(selector) {
    try {
        return document.querySelectorAll(selector).length
    } catch (_) {
        return 0
    }
}

const isUseful = (element) =>
    element instanceof Element && !element.matches(UI_SELECTOR) && !element.closest(UI_SELECTOR) && element !== document.body && element !== document.documentElement

/**
 * Filament renders ids for many components (e.g. form inputs); Livewire and
 * Alpine also generate throwaway ids. Skip the generated ones.
 */
const isStableId = (id) => Boolean(id) && !/^(lw-|wire|alpine|_x_|:r|radix|headlessui)/i.test(id) && !/^\d+$/.test(id) && !/-[0-9a-f]{8,}$/i.test(id)

/**
 * Shortest unique CSS path: walk up from the element adding `tag:nth-of-type`
 * segments until the selector matches exactly one element.
 */
export function cssPath(element) {
    const segments = []
    let current = element

    while (current && current.nodeType === Node.ELEMENT_NODE && current !== document.body) {
        let segment = current.tagName.toLowerCase()

        if (isStableId(current.id)) {
            segments.unshift(`#${cssEscape(current.id)}`)

            break
        }

        const parent = current.parentElement

        if (parent) {
            const siblings = Array.from(parent.children).filter((child) => child.tagName === current.tagName)

            if (siblings.length > 1) {
                segment += `:nth-of-type(${siblings.indexOf(current) + 1})`
            }
        }

        segments.unshift(segment)

        const candidate = segments.join(' > ')

        if (countMatches(candidate) === 1) {
            return candidate
        }

        current = parent
    }

    return segments.join(' > ')
}

/**
 * Describe the Filament component that most likely renders the element, for
 * the "add ->tourTarget()" hint. Heuristic only; never used for targeting.
 */
export function describeComponent(element) {
    const field = element.closest('.fi-fo-field, [data-field-wrapper]')

    if (field) {
        const label = field.querySelector('label')?.textContent?.trim()

        return { kind: 'form field', label, example: `TextInput::make('${slug(label) || 'name'}')->tourTarget('${slug(label) || 'name'}')` }
    }

    if (element.closest('th, .fi-ta-header-cell')) {
        const label = element.closest('th, .fi-ta-header-cell')?.textContent?.trim()

        return { kind: 'table column', label, example: `TextColumn::make('${slug(label) || 'name'}')->tourTarget('${slug(label) || 'name'}')` }
    }

    if (element.closest('button, a.fi-btn, .fi-btn, .fi-icon-btn, .fi-link')) {
        const label = element.closest('button, a, .fi-btn')?.textContent?.trim()

        return { kind: 'action', label, example: `Action::make('${slug(label) || 'action'}')->tourTarget('${slug(label) || 'action'}')` }
    }

    if (element.closest('.fi-sidebar-item, .fi-sidebar-nav')) {
        const label = element.closest('.fi-sidebar-item')?.textContent?.trim()

        return { kind: 'navigation item', label, example: `NavigationItem::make('${label || 'Orders'}')->tourTarget('${slug(label) || 'nav'}')` }
    }

    if (element.closest('.fi-section, .fi-sc-section')) {
        const label = element.closest('.fi-section, .fi-sc-section')?.querySelector('h2, h3, .fi-section-header-heading')?.textContent?.trim()

        return { kind: 'section', label, example: `Section::make('${label || 'Details'}')->tourTarget('${slug(label) || 'section'}')` }
    }

    return { kind: 'component', label: null, example: `->tourTarget('${slug(element.textContent?.trim()?.slice(0, 30)) || 'my-target'}')` }
}

const slug = (text) =>
    String(text ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40)

/**
 * Capture the best target for an element following the priority order.
 * Returns { target_type, target, selector, score, strategy, hint, matches }.
 */
export function captureTarget(element) {
    if (!(element instanceof Element)) return null

    const withScore = (result) => {
        const matches = countMatches(result.selector)

        if (matches !== 1) {
            return {
                ...result,
                matches,
                score: 'red',
                hint: matches === 0 ? 'The selector does not match anything.' : `The selector matches ${matches} elements; the first one would be highlighted.`,
            }
        }

        return { ...result, matches }
    }

    const tourTarget = element.closest('[data-tour]')

    if (tourTarget) {
        const key = tourTarget.getAttribute('data-tour')

        return withScore({
            target_type: 'data_tour',
            target: key,
            selector: attributeSelector('data-tour', key),
            score: 'green',
            strategy: 'data-tour',
            hint: null,
        })
    }

    const withId = element.closest('[id]')

    if (withId && isStableId(withId.id)) {
        return withScore({
            target_type: 'css',
            target: `#${cssEscape(withId.id)}`,
            selector: `#${cssEscape(withId.id)}`,
            score: 'green',
            strategy: 'id',
            hint: null,
        })
    }

    const wireKeyed = element.closest('[wire\\:key]')

    if (wireKeyed) {
        const key = wireKeyed.getAttribute('wire:key')
        const selector = attributeSelector('wire:key', key)

        return withScore({
            target_type: 'css',
            target: selector,
            selector,
            score: 'amber',
            strategy: 'wire:key',
            hint: 'wire:key values are usually stable within a page, but may change between Filament versions.',
        })
    }

    const path = cssPath(element)
    const component = describeComponent(element)

    return withScore({
        target_type: 'css',
        target: path,
        selector: path,
        score: 'red',
        strategy: 'css-path',
        hint: `Fragile generated path. Add ->tourTarget('key') to the ${component.kind}${component.label ? ` "${component.label}"` : ''}, e.g. ${component.example}`,
    })
}

const toParagraphs = (text) => {
    const trimmed = String(text ?? '').trim()

    if (trimmed === '') return ''

    if (/<[a-z][\s\S]*>/i.test(trimmed)) return trimmed

    return trimmed
        .split(/\n{2,}/)
        .map((paragraph) => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
        .join('')
}

const emptyDraft = () => ({
    id: null,
    title: '',
    body: '',
    placement: 'auto',
    target_type: 'none',
    target: null,
    selector: null,
    score: null,
    strategy: null,
    hint: null,
    matches: 0,
})

export function infinitoOnboardingRecorder(config = {}) {
    let hoverHandler = null
    let clickHandler = null
    let keyHandler = null
    let scrollHandler = null
    let hoveredElement = null

    return {
        tour: config.tour ?? null,
        steps: Array.isArray(config.steps) ? config.steps.map((step) => ({ ...step })) : [],
        labels: config.labels ?? {},
        exitUrl: config.exitUrl ?? null,
        picking: false,
        panelOpen: true,
        editorOpen: false,
        editingIndex: null,
        draft: emptyDraft(),
        hover: { visible: false, top: 0, left: 0, width: 0, height: 0, label: '', score: 'red' },
        dragIndex: null,
        status: null,
        dirty: false,
        previewing: false,

        init() {
            keyHandler = (event) => {
                if (event.key !== 'Escape') return

                if (this.previewing) return

                if (this.picking) {
                    event.preventDefault()
                    this.stopPicking()

                    return
                }

                if (this.editorOpen) {
                    event.preventDefault()
                    this.cancelEdit()

                    return
                }

                this.exit()
            }

            window.addEventListener('keydown', keyHandler)

            this.$watch('dirty', () => {
                if (this.dirty) this.status = null
            })
        },

        destroy() {
            this.stopPicking()
            window.removeEventListener('keydown', keyHandler)
        },

        /*
        |------------------------------------------------------------------
        | Picking
        |------------------------------------------------------------------
        */

        startPicking() {
            if (this.picking) return

            this.picking = true
            document.body.classList.add('io-recorder-picking')

            hoverHandler = (event) => this.onHover(event)
            clickHandler = (event) => this.onClick(event)
            scrollHandler = () => this.positionHover()

            document.addEventListener('mousemove', hoverHandler, true)
            document.addEventListener('click', clickHandler, true)
            window.addEventListener('scroll', scrollHandler, true)
            window.addEventListener('resize', scrollHandler)
        },

        stopPicking() {
            if (!this.picking) return

            this.picking = false
            this.hover.visible = false
            hoveredElement = null
            document.body.classList.remove('io-recorder-picking')

            document.removeEventListener('mousemove', hoverHandler, true)
            document.removeEventListener('click', clickHandler, true)
            window.removeEventListener('scroll', scrollHandler, true)
            window.removeEventListener('resize', scrollHandler)
        },

        togglePicking() {
            this.picking ? this.stopPicking() : this.startPicking()
        },

        onHover(event) {
            const element = document.elementFromPoint(event.clientX, event.clientY)

            if (!isUseful(element)) {
                this.hover.visible = false
                hoveredElement = null

                return
            }

            if (element === hoveredElement) {
                return
            }

            hoveredElement = element

            const captured = captureTarget(element)

            this.hover.label = captured ? `${captured.selector}` : ''
            this.hover.score = captured?.score ?? 'red'
            this.positionHover()
            this.hover.visible = true
        },

        positionHover() {
            if (!hoveredElement) return

            const rect = hoveredElement.getBoundingClientRect()

            this.hover.top = rect.top
            this.hover.left = rect.left
            this.hover.width = rect.width
            this.hover.height = rect.height
        },

        onClick(event) {
            const element = document.elementFromPoint(event.clientX, event.clientY)

            if (!isUseful(element)) return

            event.preventDefault()
            event.stopPropagation()
            event.stopImmediatePropagation()

            this.capture(element)
        },

        capture(element) {
            const captured = captureTarget(element)

            if (!captured) return

            this.stopPicking()

            this.draft = {
                ...emptyDraft(),
                ...captured,
                title: '',
                body: '',
                placement: 'auto',
            }
            this.editingIndex = null
            this.editorOpen = true
            this.panelOpen = true

            this.$nextTick(() => this.$refs.title?.focus())
        },

        addCentredStep() {
            this.stopPicking()
            this.draft = emptyDraft()
            this.editingIndex = null
            this.editorOpen = true
            this.$nextTick(() => this.$refs.title?.focus())
        },

        /*
        |------------------------------------------------------------------
        | Steps
        |------------------------------------------------------------------
        */

        saveDraft() {
            if (!this.draft.title.trim()) {
                this.$refs.title?.focus()

                return
            }

            const step = {
                id: this.draft.id,
                title: this.draft.title.trim(),
                body: toParagraphs(this.draft.body),
                placement: this.draft.placement,
                target_type: this.draft.target_type,
                target: this.draft.target_type === 'none' ? null : this.draft.target,
                selector: this.draft.target_type === 'none' ? null : this.draft.selector,
                score: this.draft.score,
                strategy: this.draft.strategy,
                hint: this.draft.hint,
            }

            if (this.editingIndex === null) {
                this.steps.push(step)
            } else {
                this.steps.splice(this.editingIndex, 1, step)
            }

            this.dirty = true
            this.cancelEdit()
        },

        editStep(index) {
            const step = this.steps[index]

            if (!step) return

            this.draft = { ...emptyDraft(), ...step, body: step.body ?? '' }
            this.editingIndex = index
            this.editorOpen = true
            this.$nextTick(() => this.$refs.title?.focus())
        },

        retarget(index) {
            this.editStep(index)
            this.startPicking()
            this.editorOpen = false
        },

        removeStep(index) {
            this.steps.splice(index, 1)
            this.dirty = true
        },

        cancelEdit() {
            this.editorOpen = false
            this.editingIndex = null
            this.draft = emptyDraft()
        },

        moveStep(from, to) {
            if (from === to || from < 0 || to < 0 || from >= this.steps.length || to >= this.steps.length) return

            const [step] = this.steps.splice(from, 1)
            this.steps.splice(to, 0, step)
            this.dirty = true
        },

        onDragStart(index, event) {
            this.dragIndex = index
            event.dataTransfer?.setData('text/plain', String(index))
            event.dataTransfer && (event.dataTransfer.effectAllowed = 'move')
        },

        onDrop(index, event) {
            event.preventDefault()

            if (this.dragIndex === null) return

            this.moveStep(this.dragIndex, index)
            this.dragIndex = null
        },

        /*
        |------------------------------------------------------------------
        | Preview / save / exit
        |------------------------------------------------------------------
        */

        async preview() {
            if (this.steps.length === 0 || this.previewing) return

            const factory = window.InfinitoOnboarding?.createTourRunner

            if (typeof factory !== 'function') {
                console.warn('[infinito-onboarding] tour runtime not loaded; cannot preview.')

                return
            }

            this.previewing = true
            this.panelOpen = false

            const runner = factory({
                tour: this.tour,
                steps: this.steps.map((step, order) => ({ ...step, order })),
                labels: this.labels.tour ?? {},
                onCompleted: () => this.afterPreview(),
                onDismissed: () => this.afterPreview(),
            })

            await runner.start()
        },

        afterPreview() {
            this.previewing = false
            this.panelOpen = true
        },

        async save() {
            const payload = this.steps.map((step, order) => ({
                id: step.id ?? null,
                order,
                title: step.title,
                body: step.body ?? '',
                placement: step.placement ?? 'auto',
                target_type: step.target_type ?? 'none',
                target: step.target_type === 'none' ? null : step.target,
                extra: {
                    strategy: step.strategy ?? null,
                    score: step.score ?? null,
                },
            }))

            this.status = { type: 'saving', text: this.labels.saving ?? 'Saving…' }

            try {
                const saved = await this.$wire.saveSteps(payload)

                if (Array.isArray(saved)) {
                    this.steps = saved.map((step) => ({ ...step }))
                }

                this.dirty = false
                this.status = { type: 'saved', text: this.labels.saved ?? 'Saved' }
            } catch (error) {
                console.error('[infinito-onboarding] saving steps failed:', error)
                this.status = { type: 'error', text: this.labels.save_failed ?? 'Saving failed' }
            }
        },

        exit() {
            if (this.dirty && !window.confirm(this.labels.confirm_exit ?? 'You have unsaved steps. Leave record mode anyway?')) {
                return
            }

            this.stopPicking()

            if (this.exitUrl) {
                window.location.assign(this.exitUrl)
            }
        },

        scoreClass(score) {
            return {
                'io-score-green': score === 'green',
                'io-score-amber': score === 'amber',
                'io-score-red': score === 'red' || !score,
            }
        },
    }
}

function register(Alpine) {
    Alpine.data('infinitoOnboardingRecorder', infinitoOnboardingRecorder)
}

if (window.Alpine) {
    register(window.Alpine)
} else {
    document.addEventListener('alpine:init', () => register(window.Alpine))
}

window.InfinitoOnboarding = Object.assign(window.InfinitoOnboarding ?? {}, {
    recorder: infinitoOnboardingRecorder,
    captureTarget,
    cssPath,
    describeComponent,
})
