# CLAUDE.md — contract for working on `arzcode/infinito-onboarding`

This file is the single source of truth for conventions in this repository. Every session must read it before touching code, and every PR must respect it.

## What this package is

**Infinito Onboarding** is an open-source Filament 5 plugin that announces *what's new* in a panel to existing users through guided tours and changelog modals. Tours live in the database, are versioned, are shown once per user per version, and are authored **visually** (record mode: click the element, write the copy) instead of by hand-writing CSS selectors.

- Composer name: `arzcode/infinito-onboarding`
- PHP namespace: `Arzcode\InfinitoOnboarding`
- Config file: `config/infinito-onboarding.php` (`config('infinito-onboarding.*')`)
- View namespace: `infinito-onboarding::`
- Plugin id: `infinito-onboarding`
- JS engine: [Driver.js](https://driverjs.com) (MIT), bundled locally

Targets: **PHP 8.3+, Laravel 12+, Filament 5, Livewire 4**.

## Filament 5 conventions (non-negotiable)

1. **Plugin contract.** `InfinitoOnboardingPlugin` implements `Filament\Contracts\Plugin` with `make()`, `getId()`, `register(Panel $panel)` and `boot(Panel $panel)`. Panel-specific wiring (render hooks, resources, pages) is done from the plugin, never from the service provider. Package-wide wiring (assets, macros, commands, migrations) is done from the service provider.
2. **Assets.** Register JS/CSS from the service provider's `packageBooted()` with `FilamentAsset::register([...], package: 'arzcode/infinito-onboarding')`. Use `Js::make()` / `Css::make()` pointing at files in `resources/dist/`. Alpine components are registered as `AlpineComponent::make()`. Consumers run `php artisan filament:assets`.
3. **Render hooks.** Always `FilamentView::registerRenderHook(PanelsRenderHook::X, fn () => ..., scopes: $panel->getId() ... )` — a hook is **always scoped to the registering panel** so multi-panel apps never double-render. Prefer registering from `InfinitoOnboardingPlugin::boot()`, where the `$panel` is known.
4. **DOM targeting.** Components are targeted through `->extraAttributes(['data-tour' => 'key'])` (or `extraInputAttributes()` / `extraEntryWrapperAttributes()` when the wrapper is not the interactive element). The public API for this is the `->tourTarget('key')` macro. **Never** rely on `.fi-*` classes as a primary targeting strategy — they change between Filament releases.
5. **Livewire 4.** Components extend `Livewire\Component`, are registered with `Livewire::component('infinito-onboarding::name', ...)` from the service provider, and rendered via `@livewire(...)` inside the render hook. Anything DOM-related must survive Livewire morphs (see JS rules).
6. **Namespaced everything.** Views under `resources/views`, translations under `resources/lang`, migrations under `database/migrations` (published, not auto-loaded in consumer apps, but auto-loaded in tests).

## Database schema

Table names come from `config('infinito-onboarding.table_names')`. Defaults:

| Config key | Default table | Key columns |
|---|---|---|
| `tours` | `onboarding_tours` | `key` (unique), `name`, `description`, `mode` (`tour` \| `changelog`), `route_pattern`, `version`, `published_at`, `starts_at`, `ends_at`, `audience` (json), `tenant_id`, `sort`, `is_active` |
| `tour_steps` | `onboarding_tour_steps` | `tour_id`, `order`, `target_type` (`data_tour` \| `css` \| `none`), `target`, `title`, `body`, `placement`, `extra` (json) |
| `tour_completions` | `onboarding_tour_completions` | `tour_id`, `user_id`, `tenant_id`, `seen_version`, `completed_at`, `dismissed_at` — **unique on** (`tour_id`, `user_id`, `tenant_id`, `seen_version`) |
| `tour_events` | `onboarding_tour_events` | analytics: `tour_id`, `step_id` (nullable), `user_id`, `tenant_id`, `version`, `event` (`view` \| `step` \| `completed` \| `dismissed` \| `target_missing`), `meta` (json), `created_at` |

Models: `Models\Tour`, `Models\TourStep`, `Models\TourCompletion`, `Models\TourEvent`. Enums: `Enums\TourMode`, `Enums\TargetType`, `Enums\Placement`, `Enums\TourEventType`. `audience` and `extra` cast to `array`; dates cast to `immutable_datetime`.

## Multi-language content

`config('infinito-onboarding.locales')` lists authoring locales; the first is the default and lives in the base columns (`name`, `description`, `title`, `body`). Other locales are stored in the `translations` JSON column (`{"es": {"title": "…"}}`) through `Concerns\HasTranslatedContent`. Always read content through `->translated('title')` (current locale → fallback → base). `Filament\Support\TranslationTabs::make()` renders per-locale tabs and returns nothing in single-language apps.

## Resolver rule (server-side, per page load)

`Support\TourResolver::resolveFor($user, $currentRoute, $tenantId)` returns the first tour (ordered by `sort`) where **all** of the following hold:

1. `is_active` is true
2. `published_at` is not null (and not in the future)
3. `now()` is within `[starts_at, ends_at]` (null bounds are open)
4. `route_pattern` matches the current route (`Str::is()`, so `*` wildcards work; null pattern matches everything)
5. the user passes the `audience` gate (roles / permissions / abilities, gracefully skipped if no permission package is present)
6. `tenant_id` equals the current tenant, or is null
7. **no completion row** exists for (`tour`, `user`, `tenant`, `version`) — bumping `version` re-shows the tour

## Targeting priority (this is the whole product)

When resolving or recording a step's element:

1. `[data-tour="key"]` — set via `->tourTarget('key')`. **The happy path.** Score: green.
2. Element `id`. Score: green.
3. `wire:key`-derived selector. Score: amber.
4. Generated shortest-unique CSS path. Score: red (flagged as fragile in the UI, with a hint naming the component to add `->tourTarget()` to).

A selector that matches more than one element is always red.

## JavaScript rules

- All JS is bundled **locally with esbuild** into `resources/dist/`. **Never** load from a CDN. The built `resources/dist/` output is committed so consumers do not need npm.
- Entry points: `resources/js/infinito-onboarding.js` (tour runtime, Alpine components `infinitoOnboardingTour` and `infinitoOnboardingHints`) and `resources/js/recorder.js` (record mode, Alpine component `infinitoOnboardingRecorder`).
- Hint mode (`TourMode::Hint`): beacons are `<button class="io-beacon">` elements appended to `body` and positioned from the target's bounding rect; per-hint dismissals live in `TourCompletion.meta.dismissed_steps`, and a completion row only counts as *seen* once `completed_at` or `dismissed_at` is set.
- Browser events emitted: `infinito-onboarding:step`, `infinito-onboarding:completed`, `infinito-onboarding:dismissed`.
- Livewire morph safety: listen for `livewire:navigated` and Livewire morph hooks (`Livewire.hook('morph.updated', ...)`) and call Driver.js `refresh()`. Missing targets are retried with backoff (5 attempts, 100 ms → 800 ms) and then skipped with a `console.warn` that includes the selector.
- Styling uses Filament's CSS custom properties (`--primary-*`, `--gray-*`) so light/dark themes just work.
- `npm run build` must be run and the dist committed whenever `resources/js` or `resources/css` change.

## Quality gates

- Every PR keeps **Pest green** (`vendor/bin/pest`) and **Pint clean** (`vendor/bin/pint --test`). PHPStan runs at level 5 (`vendor/bin/phpstan analyse`).
- Tests use Orchestra Testbench with an in-memory SQLite database and a real Filament panel registered in `tests/TestCase.php`.
- No feature lands without tests for its behaviour. Resolver branches, in particular, need a failing-if-removed test each.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`).

## Query flags

- `?onboarding-preview=1` forces the overlay to run for the current user regardless of seen-state (authorised users only).
- `?onboarding-record=<tour-key>` activates record mode (authorised users only).

## Testing gotchas

- **Provider order matters.** `Filament\Support\SupportServiceProvider` must be registered *before* `Livewire\LivewireServiceProvider` in `tests/TestCase.php`. Filament rebinds Livewire's `DataStore` to its own subclass; if Livewire registers first, every `app(DataStore::class)` call returns a fresh instance and component state (error bags, etc.) vanishes with a `ViewErrorBag::put(): Argument #2 must be of type MessageBag, null given` error.
- Rendered-HTML assertions use Livewire fixtures in `tests/Fixtures/Livewire` (`FormFixture`, `TableFixture`) and `Livewire::test(...)->assertSeeHtml(...)`.

## Record mode notes

- `Livewire\TourRecorder` is rendered by the same BODY_END hook as the overlay when `?onboarding-record=<key>` is present **and** the plugin's `authorize()` closure passes. An unknown key creates an unpublished draft tour scoped to the current path.
- `resources/dist/recorder.js` is registered with `->loadedOnRequest()` and injected as a `<script>` by the recorder view only, so ordinary pages never load it. It depends on `window.InfinitoOnboarding.createTourRunner` from the main bundle for its Preview button.
- Selector scoring lives in `captureTarget()` (`resources/js/recorder.js`): `data-tour` → green, stable `id` → green, `wire:key` → amber, generated `tag:nth-of-type` path → red (plus a hint naming the Filament component to add `->tourTarget()` to). Anything matching ≠ 1 element is red.
- Steps are persisted through `TourRecorder::saveSteps()` which validates, keeps ids it is given, reorders and deletes the rest inside a transaction.
