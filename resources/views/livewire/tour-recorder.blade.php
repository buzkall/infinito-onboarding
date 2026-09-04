<div class="io-recorder" data-io-recorder data-tour-recorder data-tour-key="{{ $tour->key }}">
    <script src="{{ $scriptSrc }}" data-navigate-once></script>

    <div
        wire:ignore
        x-data="infinitoOnboardingRecorder({
            tour: @js($tourPayload),
            steps: @js($steps),
            labels: @js($labels),
            exitUrl: @js($exitUrl),
        })"
    >
        {{-- Hover highlight --}}
        <div
            class="io-recorder-hover"
            data-io-recorder
            x-show="picking && hover.visible"
            x-cloak
            :class="scoreClass(hover.score)"
            :style="`top:${hover.top}px;left:${hover.left}px;width:${hover.width}px;height:${hover.height}px`"
        >
            <span class="io-recorder-hover-label" x-text="hover.label"></span>
        </div>

        {{-- Collapsed launcher --}}
        <div class="io-recorder-fab" data-io-recorder x-show="! panelOpen && ! previewing" x-cloak>
            <button type="button" class="io-btn io-btn-primary" @click="panelOpen = true" x-text="labels.title"></button>
        </div>

        {{-- Floating panel --}}
        <div class="io-recorder-panel" data-io-recorder x-show="panelOpen" x-cloak>
            <div class="io-recorder-header">
                <strong x-text="labels.title"></strong>
                <span class="io-recorder-key" x-text="tour?.key"></span>
                <span class="io-spacer"></span>
                <button type="button" class="io-btn io-btn-sm" @click="panelOpen = false" aria-label="Minimise">&minus;</button>
                <button type="button" class="io-btn io-btn-sm" @click="exit()" x-text="labels.exit"></button>
            </div>

            <div class="io-recorder-body">
                <template x-if="! editorOpen">
                    <div>
                        <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem">
                            <button type="button" class="io-btn io-btn-primary" :class="{ 'io-btn-active': picking }" @click="togglePicking()" x-text="picking ? labels.picking : labels.pick"></button>
                            <button type="button" class="io-btn" @click="addCentredStep()" x-text="labels.centred"></button>
                        </div>

                        <p class="io-recorder-empty" x-show="steps.length === 0" x-text="labels.empty"></p>

                        <ol class="io-recorder-steps">
                            <template x-for="(step, index) in steps" :key="index">
                                <li
                                    class="io-recorder-step"
                                    draggable="true"
                                    :class="{ 'io-dragging': dragIndex === index }"
                                    @dragstart="onDragStart(index, $event)"
                                    @dragover.prevent
                                    @drop="onDrop(index, $event)"
                                    @dragend="dragIndex = null"
                                >
                                    <span class="io-step-index" x-text="index + 1"></span>
                                    <span class="io-score" :class="scoreClass(step.target_type === 'none' ? 'green' : step.score)"></span>
                                    <div class="io-step-main">
                                        <div class="io-step-title" x-text="step.title"></div>
                                        <div class="io-step-target" x-text="step.target_type === 'none' ? labels.no_target : (step.selector ?? step.target)"></div>
                                    </div>
                                    <div class="io-step-actions">
                                        <button type="button" class="io-btn io-btn-sm" @click="editStep(index)" x-text="labels.edit"></button>
                                        <button type="button" class="io-btn io-btn-sm" @click="removeStep(index)" x-text="labels.remove">×</button>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
                </template>

                <template x-if="editorOpen">
                    <form class="io-recorder-editor" @submit.prevent="saveDraft()">
                        <div>
                            <label x-text="labels.target"></label>
                            <div class="io-recorder-target" :class="scoreClass(draft.target_type === 'none' ? 'green' : draft.score)">
                                <span class="io-score" :class="scoreClass(draft.target_type === 'none' ? 'green' : draft.score)" style="margin-top:0"></span>
                                <code x-text="draft.target_type === 'none' ? labels.no_target : draft.selector"></code>
                                <button type="button" class="io-btn io-btn-sm" @click="editorOpen = false; startPicking()" x-text="labels.retarget"></button>
                            </div>
                            <div class="io-recorder-hint" x-show="draft.hint" :class="scoreClass(draft.score)" x-text="draft.hint"></div>
                        </div>

                        <div>
                            <label for="io-step-title" x-text="labels.step_title"></label>
                            <input id="io-step-title" type="text" x-ref="title" x-model="draft.title" required>
                        </div>

                        <div>
                            <label for="io-step-body" x-text="labels.step_body"></label>
                            <textarea id="io-step-body" x-model="draft.body"></textarea>
                        </div>

                        <div>
                            <label for="io-step-placement" x-text="labels.step_placement"></label>
                            <select id="io-step-placement" x-model="draft.placement">
                                <template x-for="(label, value) in labels.placements" :key="value">
                                    <option :value="value" x-text="label" :selected="draft.placement === value"></option>
                                </template>
                            </select>
                        </div>

                        <div style="display:flex;gap:0.5rem;justify-content:flex-end">
                            <button type="button" class="io-btn" @click="cancelEdit()" x-text="labels.cancel"></button>
                            <button type="submit" class="io-btn io-btn-primary" x-text="editingIndex === null ? labels.add_step : labels.update_step"></button>
                        </div>
                    </form>
                </template>
            </div>

            <div class="io-recorder-footer">
                <span class="io-recorder-status" :class="status ? `io-status-${status.type}` : ''" x-text="status ? status.text : (dirty ? labels.unsaved : '')"></span>
                <span class="io-spacer"></span>
                <button type="button" class="io-btn" :disabled="steps.length === 0" @click="preview()" x-text="labels.preview"></button>
                <button type="button" class="io-btn io-btn-primary" :disabled="! dirty && steps.every(s => s.id)" @click="save()" x-text="labels.save"></button>
            </div>
        </div>
    </div>
</div>
