/**
 * Record mode: visual authoring of tour steps.
 *
 * Activated by the TourRecorder Livewire component (query flag
 * `?onboarding-record=<tour-key>`, authorised users only). Hover highlights
 * the element under the cursor with the selector it would capture (arrow up
 * and down move to the parent or back to the child); clicking captures it
 * (see captureTarget()), scores it, and opens the step form. Steps are kept in
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
 * Alpine also generate throwaway ids, and some Blade components number
 * theirs (`input-1`). Skip the generated ones.
 */
const isStableId = (id) =>
    Boolean(id) && !/^(lw-|wire|alpine|_x_|:r|radix|headlessui)/i.test(id) && !/^\d+$/.test(id) && !/-[0-9a-f]{8,}$/i.test(id) && !/^[a-z]+-\d+$/i.test(id)

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
 * Livewire generates wire:key values such as `lw-2767091022-0-0` and embeds
 * 20-character component ids in others; neither survives a reload.
 */
const isStableWireKey = (key) => Boolean(key) && !/^lw-/.test(key) && !/[A-Za-z0-9]{20,}/.test(key)

const urlPath = (value) => {
    try {
        const url = new URL(value, window.location.href)

        return url.origin === window.location.origin ? url.pathname + url.search : null
    } catch (_) {
        return null
    }
}

/**
 * Ways to identify an element by its own attributes, best first. Nothing here
 * looks at ancestors: an id on <main> must never stand in for a card inside it.
 */
export function identitiesOf(element) {
    const identities = []
    const add = (identity) => identities.push({ target_type: 'css', hint: null, ...identity, target: identity.target ?? identity.selector })

    const tourKey = element.getAttribute('data-tour')

    if (tourKey) {
        add({ target_type: 'data_tour', target: tourKey, selector: attributeSelector('data-tour', tourKey), score: 'green', strategy: 'data-tour' })
    }

    if (isStableId(element.id)) {
        add({ selector: `#${cssEscape(element.id)}`, score: 'green', strategy: 'id' })
    }

    // Livewire 4 stamps component roots (widgets, relation managers…) with their class name.
    const componentName = element.getAttribute('wire:name')

    if (componentName) {
        add({ selector: attributeSelector('wire:name', componentName), score: 'green', strategy: 'livewire-component' })
    }

    const wireKey = element.getAttribute('wire:key')

    if (isStableWireKey(wireKey)) {
        add({ selector: attributeSelector('wire:key', wireKey), score: 'amber', strategy: 'wire:key', hint: 'wire:key values are usually stable within a page, but may change between Filament versions.' })
    }

    const wireClick = element.getAttribute('wire:click')

    if (wireClick) {
        add({ selector: attributeSelector('wire:click', wireClick), score: 'amber', strategy: 'wire:click', hint: null })
    }

    const tag = element.tagName.toLowerCase()
    const href = tag === 'a' ? urlPath(element.getAttribute('href')) : null

    if (href && href !== '/' && !href.startsWith('/#')) {
        add({ selector: `a[href$="${href.replace(/["\\]/g, '\\$&')}"]`, score: 'amber', strategy: 'href', hint: null })
    }

    const action = tag === 'form' ? urlPath(element.getAttribute('action')) : null

    if (action) {
        add({ selector: `form[action$="${action.replace(/["\\]/g, '\\$&')}"]`, score: 'amber', strategy: 'form-action', hint: null })
    }

    const name = ['input', 'select', 'textarea', 'button'].includes(tag) ? element.getAttribute('name') : null

    if (name) {
        add({ selector: `${tag}${attributeSelector('name', name)}`, score: 'amber', strategy: 'name', hint: null })
    }

    return identities
}

const uniqueIdentity = (element) => identitiesOf(element).find((identity) => countMatches(identity.selector) === 1) ?? null

/**
 * Two boxes are the same thing on screen when every edge is within a few
 * pixels: a widget root, its <section> and the section's content wrapper.
 */
const sameBox = (a, b) => {
    const ra = a.getBoundingClientRect()
    const rb = b.getBoundingClientRect()

    return Math.abs(ra.top - rb.top) <= 4 && Math.abs(ra.left - rb.left) <= 4 && Math.abs(ra.right - rb.right) <= 4 && Math.abs(ra.bottom - rb.bottom) <= 4
}

const area = (element) => {
    const rect = element.getBoundingClientRect()

    return rect.width * rect.height
}

const segmentFor = (element) => {
    const tag = element.tagName.toLowerCase()
    const siblings = element.parentElement ? Array.from(element.parentElement.children).filter((child) => child.tagName === element.tagName) : []

    return siblings.length > 1 ? `${tag}:nth-of-type(${siblings.indexOf(element) + 1})` : tag
}

/** Deeper paths from an anchor break as easily as a path from the document. */
const MAX_ANCHOR_DEPTH = 3

/**
 * Selector relative to an identified ancestor, using the shortest tail of
 * the path that is already unique inside the page.
 */
const anchoredPath = (anchorSelector, anchor, element) => {
    const segments = []

    for (let current = element; current && current !== anchor; current = current.parentElement) {
        segments.unshift(segmentFor(current))
    }

    // Shortest unique tail: `anchor div:nth-of-type(2) > div` rather than every wrapper in between.
    for (let depth = 1; depth < segments.length; depth++) {
        const selector = `${anchorSelector} ${segments.slice(-depth).join(' > ')}`

        if (countMatches(selector) === 1) return { selector, depth }
    }

    return { selector: [anchorSelector, ...segments].join(' > '), depth: segments.length }
}

/**
 * Capture the best target for an element. Returns
 * { target_type, target, selector, score, strategy, hint, matches }.
 *
 * 1. The element's own identity (data-tour, id, Livewire component name,
 *    wire:key, wire:click, link or form URL, input name), or that of an
 *    ancestor occupying the same box, or a data-tour wrapper hugging it.
 * 2. A path from the nearest identified ancestor: amber when it is at most
 *    MAX_ANCHOR_DEPTH levels deep, red otherwise.
 * 3. A generated path from the document (red).
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

    const component = describeComponent(element)
    const tourTargetHint = `Add ->tourTarget('key') to the ${component.kind}${component.label ? ` "${component.label}"` : ''} for a selector that survives layout changes, e.g. ${component.example}`
    const elementArea = Math.max(area(element), 1)

    // ->tourTarget() on a form field stamps the wrapper (label, input and help text),
    // so a data-tour ancestor a few times the element's size still counts as the element.
    for (let current = element; current && isUseful(current) && area(current) <= elementArea * 6; current = current.parentElement) {
        const identity = current === element || sameBox(current, element)
            ? uniqueIdentity(current)
            : identitiesOf(current).find((candidate) => candidate.strategy === 'data-tour' && countMatches(candidate.selector) === 1)

        if (identity) {
            return withScore(identity.score === 'green' || identity.hint ? identity : { ...identity, hint: tourTargetHint })
        }
    }

    for (let anchor = element.parentElement; anchor && isUseful(anchor); anchor = anchor.parentElement) {
        const identity = uniqueIdentity(anchor)

        if (identity) {
            const { selector, depth } = anchoredPath(identity.selector, anchor, element)
            const isClose = depth <= MAX_ANCHOR_DEPTH

            return withScore({
                target_type: 'css',
                target: selector,
                selector,
                score: isClose ? 'amber' : 'red',
                strategy: `inside-${identity.strategy}`,
                hint: isClose ? tourTargetHint : `Fragile generated path. ${tourTargetHint}`,
            })
        }
    }

    const path = cssPath(element)

    return withScore({
        target_type: 'css',
        target: path,
        selector: path,
        score: 'red',
        strategy: 'css-path',
        hint: `Fragile generated path. ${tourTargetHint}`,
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
    before: [],
    advance_on_click: false,
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
    // The element under the pointer; hoveredElement may be one of its ancestors after ArrowUp.
    let pointerElement = null

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
        pickingFor: null,
        hover: { visible: false, top: 0, left: 0, width: 0, height: 0, label: '', score: 'red' },
        dragIndex: null,
        status: null,
        dirty: false,
        previewing: false,

        init() {
            keyHandler = (event) => {
                if (this.picking && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
                    event.preventDefault()
                    this.climb(event.key === 'ArrowUp' ? 'parent' : 'child')

                    return
                }

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

            if (this.pickingFor === 'before') {
                // Cancelled: go back to the editor.
                this.pickingFor = null
                this.editorOpen = true
            }

            this.picking = false
            this.hover.visible = false
            hoveredElement = null
            pointerElement = null
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
                pointerElement = null

                return
            }

            if (element === pointerElement) {
                return
            }

            pointerElement = element
            this.highlight(element)
        },

        /**
         * ArrowUp selects the parent of the highlighted element, ArrowDown
         * goes back towards the element under the pointer.
         */
        climb(direction) {
            if (!hoveredElement || !pointerElement) return

            if (direction === 'parent') {
                const parent = hoveredElement.parentElement

                if (isUseful(parent)) this.highlight(parent)

                return
            }

            let child = pointerElement

            while (child && child.parentElement !== hoveredElement) {
                child = child.parentElement
            }

            if (child && hoveredElement !== pointerElement) this.highlight(child)
        },

        highlight(element) {
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
            const pointed = document.elementFromPoint(event.clientX, event.clientY)

            if (!isUseful(pointed)) return

            // Keep the ancestor chosen with ArrowUp when the click lands inside it.
            const element = hoveredElement && hoveredElement.contains(pointed) ? hoveredElement : pointed

            event.preventDefault()
            event.stopPropagation()
            event.stopImmediatePropagation()

            this.capture(element)
        },

        capture(element) {
            const captured = captureTarget(element)

            if (!captured) return

            const pickingFor = this.pickingFor
            this.pickingFor = null
            this.stopPicking()

            if (pickingFor === 'before') {
                // "Open this first": record a click precondition on the draft
                // instead of replacing it.
                this.pickingFor = null
                this.draft.before = [
                    ...(this.draft.before ?? []),
                    { type: 'click', target_type: captured.target_type, target: captured.target, selector: captured.selector, score: captured.score },
                ]
                this.editorOpen = true
                this.panelOpen = true

                return
            }

            const keep = this.editingIndex !== null ? { before: this.draft.before ?? [], advance_on_click: this.draft.advance_on_click ?? false } : {}

            this.draft = {
                ...emptyDraft(),
                ...captured,
                title: this.editingIndex !== null ? this.draft.title : '',
                body: this.editingIndex !== null ? this.draft.body : '',
                placement: this.editingIndex !== null ? this.draft.placement : 'auto',
                ...keep,
            }
            this.editorOpen = true
            this.panelOpen = true

            this.$nextTick(() => this.$refs.title?.focus())
        },

        pickBefore() {
            this.pickingFor = 'before'
            this.editorOpen = false
            this.startPicking()
        },

        removeBefore(index) {
            this.draft.before = (this.draft.before ?? []).filter((_, i) => i !== index)
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
                before: (this.draft.before ?? []).map((action) => ({ ...action })),
                advance_on_click: Boolean(this.draft.advance_on_click),
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

            this.draft = { ...emptyDraft(), ...step, body: step.body ?? '', before: (step.before ?? []).map((action) => ({ ...action })), advance_on_click: Boolean(step.advance_on_click) }
            this.editingIndex = index
            this.editorOpen = true
            this.$nextTick(() => this.$refs.title?.focus())
        },

        retarget(index) {
            this.editStep(index)
            this.editorOpen = false
            this.startPicking()
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
                    before: (step.before ?? []).map(({ type, target_type, target, selector, timeout }) => ({ type, target_type, target, selector, timeout })),
                    advance_on_click: Boolean(step.advance_on_click),
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
