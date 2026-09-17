# Changelog

All notable changes to `arzcode/infinito-onboarding` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- **Record steps** action on the tour table, the edit page and the Steps relation manager: opens record mode on the tour's page, or asks for the page when the route pattern matches several.
- **Record a tour** action on the tours list: creates an unpublished draft for a page and opens record mode on it.

### Changed

- Rector (`rector.php`) now runs in CI alongside Pint and PHPStan; the codebase was refactored with it (no behaviour changes).

### Fixed

- Record mode captured the nearest ancestor with an id, so picking any card on a page recorded `#fi-main-content`. It now identifies the picked element itself (Livewire component name for widgets, `wire:click`, link URL, input name, or a short path inside one of those), ignores Livewire-generated `wire:key` values and numbered ids, and lets you move to the parent / child with ↑ / ↓ while picking.
- The changelog modal's "Got it" button did nothing: `@js()` is not compiled inside component tag attributes, so its click handler was invalid JavaScript.
- Tenant-scoped tours now resolve in tenant panels, and a tour for every tenant is no longer shown again after it was completed inside a tenant (the overlay dropped the tenant id).
- Registering the resource in a panel with tenancy no longer breaks every tour query (`TourResource` is not scoped through Filament's ownership relationship any more).
- Route pattern lists without wildcards (`->route('admin/orders', 'admin/users')`) and patterns saved with a leading slash now match.
- The recorder's exit link stays on the recorded page after Livewire requests.

### Security

- Step bodies are sanitised before they are rendered as HTML, and popover titles are escaped.
- Inside a tenant, the resource and record mode only manage that tenant's tours; preview by key ignores other tenants' tours.
- Browser-reported analytics events are rate limited per user and tour (`analytics.max_events_per_minute`, default 60).

- Popover, beacon and record-mode colours now read Filament 4+/5 colour variables correctly (`var(--primary-600)` holds a full `oklch()` colour, not an RGB triplet), so the Next / Got it buttons and the picker outline are visible.
- Record mode shows the "Open this first" and "Advance when the element is clicked" controls (their labels were missing).
- The resolver and the "What's new" badge check the seen-state of all candidate tours in one query, and the analytics summary is aggregated in the database instead of loading missing-target events into memory.
- `TourDefinition::save()` updates existing steps in place by position instead of recreating them, so step ids and per-step analytics survive re-running a code-first definition.
- The changelog modal no longer repeats a single tour's name under the modal heading.

### Added

- `php artisan infinito-onboarding:install`: publishes the migrations, optionally the config, runs the migrations, publishes the Filament assets and registers the plugin in the panel providers you choose.
- `php artisan infinito-onboarding:uninstall`: unregisters the plugin and deletes the published assets, then optionally drops the tables, deletes the published migrations, config, translations and views, and runs `composer remove`.
- `onboarding:forget-user {id}` command and `Support\UserData::forget()` to erase a user's completions and events.
- Spanish translation.
- Index on `(user_id, tenant_id)` for the events table: publish the new `add_user_index_to_onboarding_tour_events_table` migration.
- README screenshots and a Testbench workbench (`composer workbench:reset`, `composer workbench:serve`, `node tests/browser/screenshots.js`) to regenerate them.

## [1.0.0] - 2026-09-05

First stable release. See *Stability guarantees* in the README for what is now covered by semantic versioning.

### Added

- Audience segments: named, reusable audiences defined in `config('infinito-onboarding.segments')` (criteria or closures) or with `InfinitoOnboardingPlugin::make()->segment('beta', …)`, referenced by tours through `audience.segments`, the builder's `segments()` and a *Segments* select in the resource.
- Hint mode (`TourMode::Hint`, builder `->hints()`): a pulsing beacon next to every step's target, each opening the step's popover with a "Got it" button. Dismissals are stored per user and version in the new `meta` column of the completions table (publish the new migration); the tour is completed once all hints are dismissed, and a completion row now only counts as *seen* when it carries `completed_at` or `dismissed_at`. New Alpine component `infinitoOnboardingHints`, overlay action `dismissHint()`, browser event `infinito-onboarding:hint-dismissed`.

## [0.4.0] - 2026-09-05

### Added

- Multi-language content: `translations` JSON column on tours and steps (publish the new migration), `Concerns\HasTranslatedContent` with `translated()` resolving current locale → fallback → default, `locales` / `fallback_locale` / `locale_labels` config, per-locale **Translations** tabs in the tour and step forms, builder `translate()` / `translateTour()`, and translations in the JSON interchange format. The overlay, the changelog modal and the topbar trigger render localised content.

## [0.3.0] - 2026-09-05

### Added

- Analytics: `onboarding_tour_events` table (publish the new migration) storing `view`, `step`, `completed`, `dismissed` and `target_missing` events per user / tenant / version. The overlay reports view, step and missing-target events from the browser; completions and dismissals are recorded server-side.
- `TourAnalytics::summary()` with views, unique viewers, completion rate, per-step reached / drop-off and missing selectors, shown in a widget on the tour's edit page (current version or all versions).
- `onboarding:prune-events {--days=}` command and `analytics.enabled` / `analytics.prune_after_days` config.
- Runner `onEvent` callback.

## [0.2.0] - 2026-09-05

### Added

- Step preconditions: `before` actions (`click`, `wait`, `dispatch`) run before a step is shown so targets inside modals and tabs work. Builder helpers `clickFirst()`, `waitFor()`, `dispatchFirst()`, `before()`; an *Open this first* repeater in the step form; *Pick element to click first* in record mode.
- `advance_on_click` (builder `advanceOnClick()`): clicking the highlighted element (e.g. a `wire:click` button) advances the tour once Livewire's request has settled.
- The runner now controls navigation itself (next / previous / arrow keys / overlay click), running preconditions on the way and skipping unreachable steps in both directions.
- `window.InfinitoOnboarding.runPreconditions()` and `waitForLivewireIdle()` helpers.

## [0.1.0] - 2026-09-04

First release.

### Added

- Database-managed tours (`onboarding_tours`, `onboarding_tour_steps`, `onboarding_tour_completions`) with configurable table names, versioning and once-per-user-per-version seen-state (tenant aware).
- `TourResolver`: active + published + window + route pattern + audience (roles / permissions / users) + tenant + unseen version.
- `->tourTarget('key')` macro on Filament actions, form fields, infolist entries, table columns, navigation items and layout components.
- Driver.js overlay (bundled locally with esbuild) as the `infinitoOnboardingTour` Alpine component, with Livewire-morph re-anchoring, missing-target retry/backoff and `infinito-onboarding:step|completed|dismissed` browser events.
- `TourOverlay` Livewire component injected at `BODY_END`, scoped to the registering panel, rendering nothing when no tour resolves.
- Plugin options: `enabled()`, `authorize()`, `resource()`, `navigationGroup()`, `navigationSort()`, `topbarTrigger()`.
- `TourResource` with a `StepsRelationManager` (drag-and-drop ordering), Preview and Reset seen-state actions.
- Record mode (`?onboarding-record=<key>`): element picker with hover outline, selector capture by priority (data-tour → id → wire:key → generated path), robustness scoring with `->tourTarget()` hints, floating step editor with reordering, instant preview and Livewire persistence.
- Changelog mode: modal listing steps as release notes, plus a "What's new" topbar trigger with unseen badge.
- Code-first builder `Tour::define('key')->…->save()` and `onboarding:export` / `onboarding:import` artisan commands with a JSON interchange format.
