# Infinito Onboarding

**Announce what's new in your Filament panel with DB-managed, visually authored guided tours.**

Infinito Onboarding shows your *existing* users what changed: a guided tour that highlights the new button, or a changelog modal that lists the release notes. Tours live in the database, are versioned, are shown **once per user per version**, and can be authored **by clicking on the page** (record mode) instead of hand-writing CSS selectors.

It is built on [Driver.js](https://driverjs.com) (bundled, no CDN) and targets **Filament 5, Laravel 12+, Livewire 4, PHP 8.3+**.

## How is this different from an onboarding tour?

Classic onboarding tours run once for *new* users, live in code, and break the moment a class name changes. Infinito Onboarding is made for the *what's new* problem:

| | Classic onboarding | Infinito Onboarding |
|---|---|---|
| Audience | New users | Existing users, per feature release |
| Stored in | Code | Database (with a code-first builder and JSON export for git) |
| Shown | Once | Once **per version**: bump the version and everyone sees it again |
| Targeting | CSS selectors | `->tourTarget('key')` on the Filament component, then id, `wire:key`, generated path |
| Authoring | By hand | **Record mode**: click the element, write the copy, save |
| Modes | Tour | Tour, **hints** (persistent beacons) **and** changelog modal with a "What's new" topbar button |

## Installation

```bash
composer require arzcode/infinito-onboarding
php artisan vendor:publish --tag="infinito-onboarding-migrations"
php artisan migrate
php artisan filament:assets
```

Optionally publish the config (table names, query flags, export path) and translations:

```bash
php artisan vendor:publish --tag="infinito-onboarding-config"
php artisan vendor:publish --tag="infinito-onboarding-translations"
```

Register the plugin in your panel provider:

```php
use Arzcode\InfinitoOnboarding\InfinitoOnboardingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            InfinitoOnboardingPlugin::make()
                ->resource()                                   // manage tours from the panel (opt-in)
                ->topbarTrigger()                              // "What's new" button with unseen badge (opt-in)
                ->authorize(fn (User $user) => $user->isAdmin()) // who may manage, preview and record tours
                ->enabled(fn () => ! app()->runningUnitTests()),
        );
}
```

Everything is opt-in: with only `->plugin(InfinitoOnboardingPlugin::make())` the overlay runs and nothing else is registered.

### Plugin options

| Option | Default | What it does |
|---|---|---|
| `enabled(bool\|Closure)` | `true` | Switch the whole overlay on or off. |
| `authorize(Closure)` | nobody | Receives the user. Gates the resource, `?onboarding-preview` and record mode. |
| `resource(bool\|Closure, ?string $class)` | `false` | Registers `TourResource` (pass a subclass to customise it). |
| `navigationGroup()` / `navigationSort()` | `null` | Placement of the resource in the sidebar. |
| `topbarTrigger(bool\|Closure, string $hook)` | `false` | Renders the "What's new" changelog button (hook defaults to `GLOBAL_SEARCH_AFTER`). |

## Targeting: `->tourTarget()` is the recommended approach

Filament's `.fi-*` classes change between releases; Livewire re-renders the DOM. A tour that depends on generated selectors breaks silently. The package therefore adds a `tourTarget()` macro to Filament components which stamps a stable `data-tour="key"` attribute on the element a step should highlight.

**Before** (fragile):

```php
// Step target: ".fi-ta-header-toolbar .fi-btn:nth-child(2)"
Action::make('export');
```

**After** (stable):

```php
Action::make('export')->tourTarget('export-orders');
// Step target: export-orders  →  [data-tour="export-orders"]
```

It works on actions, form fields (the whole field wrapper, label included), infolist entries, table columns (the header cell, once per column), navigation items and layout components such as sections:

```php
TextInput::make('email')->tourTarget('email-field');
TextColumn::make('status')->tourTarget('status-column');
Section::make('Billing')->tourTarget('billing-section');
NavigationItem::make('Orders')->tourTarget('nav-orders');
```

Anything else can be targeted with a raw CSS selector (`target_type = css`), and a step with `target_type = none` is shown centred with no highlight.

## Authoring tours

### 1. From the panel (TourResource)

Enable `->resource()` and you get an **Onboarding tours** resource: name, key, mode, route pattern (with your panel's routes as suggestions), version, publish toggle, start/end window, audience (roles, permissions), sort, active flag, and a **Steps** relation manager with drag-and-drop ordering, target type/target, rich-text body and placement.

Table actions:

- **Preview**: opens the tour's route with `?onboarding-preview=<key>`, which forces the tour for you regardless of seen-state (and never records a completion).
- **Reset seen-state**: deletes the tour's completions so every user sees it again.

### 2. Record mode (click, don't write selectors)

Visit any panel page as an authorised user with:

```
https://your-app.test/admin/orders?onboarding-record=orders-q3
```

If the key is new, an unpublished draft tour scoped to that path is created. A floating panel appears:

1. **Pick element**: hover shows a dotted outline and the selector that would be captured; click captures it.
2. Write the **title**, **body** and choose a **placement**; add the step.
3. Repeat, drag to reorder, **Preview** runs the tour immediately, **Save** persists through Livewire. **Esc** cancels picking / exits.

Every captured target is scored:

- 🟢 **green**: a `data-tour` attribute or a stable `id` was found.
- 🟠 **amber**: a `wire:key` ancestor was used, usually stable within a page.
- 🔴 **red**: a generated CSS path, or a selector matching more than one element. The panel tells you which Filament component to add `->tourTarget('key')` to.

Publish the tour from the resource (or with the builder) when you are happy.

### 3. Code-first (lives in git)

```php
use Arzcode\InfinitoOnboarding\Models\Tour;

Tour::define('q3-2026')
    ->name('Q3 release')
    ->route('admin/orders*')          // several patterns: ->route('a*', 'b*')
    ->version('2.4.0')
    ->roles(['admin', 'editor'])      // ->permissions(), ->users(), ->tenant()
    ->step('export-orders', 'Export orders', 'You can now export to CSV and XLSX.')
    ->step('status-column', 'Status at a glance', 'Colours follow the order state.', 'bottom')
    ->note('That is it for now', '<p>Thanks for reading.</p>')   // centred step
    ->publish()                       // or ->publish('2026-10-01 09:00')
    ->save();
```

`save()` upserts by key and replaces the steps. Run it from a seeder or a deploy hook: running it twice changes nothing, and completions are kept, so bumping `version()` is what makes the tour appear again.

### 4. Import / export (promote between environments)

```bash
php artisan onboarding:export              # writes database/tours/<key>.json for every tour
php artisan onboarding:export orders-q3    # a single tour
php artisan onboarding:import              # syncs JSON → DB by key, never duplicates
php artisan onboarding:import --fresh      # also deletes tours missing from the directory
```

The directory comes from `config('infinito-onboarding.export_path')`; both commands accept `--path=`. The JSON format has no ids, so a tour recorded on staging can be committed and imported in production.

## Hint mode (beacons)

Set `mode = hint` (or `->hints()` on the builder) and, instead of a guided tour, every step's target gets a **pulsing dot**. Clicking a dot highlights the element with the step's title and body and a **Got it** button; each dismissed hint disappears and stays dismissed per user and version, and the tour counts as completed once every hint is gone. Hints are persistent: they survive reloads until dismissed, which suits "there is a new button here" announcements that should not interrupt.

```php
Tour::define('new-toolbar')->hints()
    ->step('export-orders', 'Export orders', 'Download CSV or XLSX.')
    ->step('bulk-actions', 'Bulk actions', 'Select rows to see them.')
    ->publish()->save();
```

The browser event `infinito-onboarding:hint-dismissed` fires per hint. Steps without a target are ignored in hint mode.

## Steps inside modals and tabs (preconditions)

A step can run actions **before** it is shown, so targets that only exist once a modal or tab is open still work:

```php
Tour::define('settings')
    ->step('open-settings', 'Settings live here')->advanceOnClick()      // clicking the button moves on
    ->step('settings-modal-title', 'Your new settings')
        ->clickFirst('open-settings')                                    // opens the modal first
        ->waitFor('#settings-modal', 5000)                               // then waits for it (optional)
    ->save();
```

- `clickFirst()`, `waitFor()` and `dispatchFirst('event', [...])` apply to the last added step; pass a `->tourTarget()` key or a CSS selector.
- `advanceOnClick()` lets the user click the highlighted element (a `wire:click` button, a link) and moves to the next step once Livewire has finished its request.
- In the resource, the step form has an **Open this first** repeater and an **Advance when the element is clicked** toggle. In record mode, the editor offers **Pick element to click first**.
- Targets that are still missing after their preconditions are skipped with a console warning, in both directions.

## Multi-language content

Set the locales you author in; the first one is the default and is stored in the normal columns, the others in a `translations` JSON column:

```php
// config/infinito-onboarding.php
'locales' => ['en', 'es'],
'locale_labels' => ['es' => 'Español'],
```

The tour and step forms then show a **Translations** tab per extra locale. At runtime the overlay uses the current app locale, then `fallback_locale` (defaults to the app's), then the default. No translation package is required; the builder and the JSON format carry translations too:

```php
Tour::define('q3')
    ->translateTour('es', ['name' => 'Novedades del Q3'])
    ->step('export-orders', 'Export orders', 'CSV and XLSX.')
        ->translate('es', ['title' => 'Exportar pedidos', 'body' => 'CSV y XLSX.'])
    ->save();
```

Read content through `$step->translated('title')` in your own code.

## Analytics

Every tour records events (views, highlighted steps, completions, dismissals and *target not found* skips) per user, tenant and version. The tour's edit page shows a widget with views, unique viewers, completion rate, a per-step **reached / drop-off** table and the list of **missing selectors** with how often they failed, so you know which component still needs a `->tourTarget()`.

- Disable with `'analytics' => ['enabled' => false]` in the config. Preview and record mode never record events.
- Retention: `php artisan onboarding:prune-events` deletes events older than `analytics.prune_after_days` (default 90). Schedule it daily.
- Query the data yourself through `Arzcode\InfinitoOnboarding\Models\TourEvent` or `Support\TourAnalytics::summary($tour)`.

## Changelog mode

Set `mode = changelog` (resource) or call `->changelog()` on the builder. Instead of Driver.js, the tour renders as a single modal listing its steps as release-note entries (title + body). It uses the same resolver and seen-state, so it also shows once per user per version.

With `->topbarTrigger()` a "What's new" icon button appears in the topbar with a dot while an unseen changelog exists. Users can re-open the latest changelogs from it at any time; "Got it" marks them seen.

## When does a tour show? (resolver rule)

On every panel page load the package picks the first tour, ordered by `sort`, where **all** of the following hold:

1. `is_active`
2. `published_at` is set and not in the future
3. now is within `starts_at` / `ends_at` (null bounds are open)
4. the route pattern matches the current path (`Str::is`, so `admin/orders*` works; comma-separate several; empty = every page)
5. the user passes the audience gate (see below)
6. `tenant_id` equals the current tenant or is null
7. there is no completion row for the tour's **current version**

Nothing at all is rendered when no tour resolves, so pages without a tour carry no extra Livewire component.

### Audience

The `audience` JSON column may contain:

```json
{ "roles": ["admin"], "permissions": ["export orders"], "users": [1, 2] }
```

Each present criterion must pass (any of its values). Roles use `hasAnyRole()` / `hasRole()` when your user model provides them (spatie/laravel-permission) and fall back to a `roles` attribute or relation; permissions go through Laravel's `Gate`, so plain policies work and nothing breaks when no permission package is installed.

### Multi-tenancy

Completions are stored per tenant (`Filament::getTenant()`), so a user sees a tour once per tenant. A tour with a `tenant_id` only shows in that tenant; tours without one show everywhere. User ids are stored as strings, so UUID and integer keys both work.

### Authorisation

- The **overlay** shows to any authenticated panel user that the resolver selects.
- The **resource**, **`?onboarding-preview`** and **record mode** are only available to users passing the plugin's `authorize()` closure. By default that is nobody.
- Record mode and preview never persist seen-state.

## Events (browser)

The overlay dispatches `infinito-onboarding:step`, `infinito-onboarding:completed` and `infinito-onboarding:dismissed` on `window` (`event.detail` carries the tour and step payload) so you can hook analytics.

## Compatibility

| Package | Version |
|---|---|
| PHP | 8.3, 8.4 |
| Laravel | 12, 13 |
| Filament | 5.x |
| Livewire | 4.x |
| Driver.js | 1.8 (bundled) |

### Upgrading

See [CHANGELOG.md](CHANGELOG.md). Migrations are published, so run `php artisan vendor:publish --tag="infinito-onboarding-migrations"` again after upgrades that add columns, and `php artisan filament:assets` after every upgrade.

## Troubleshooting: "my step doesn't appear"

- **Unpublished / inactive / out of window**: check `published_at`, `is_active`, `starts_at`, `ends_at` in the resource. Preview with `?onboarding-preview=<key>` bypasses seen-state but *not* the target lookup, so it is the fastest way to check the page itself.
- **Already seen**: completions are per user, tenant and version. Bump the version or use *Reset seen-state*.
- **Route pattern**: it is matched against the request path (`admin/orders/12/edit`), not the route name. Use `admin/orders*`.
- **Missing target**: the browser retries five times (100 → 800 ms) then skips the step and logs `target not found … (selector: …)` in the console. Add `->tourTarget()` to the component or fix the selector. Elements inside closed modals or inactive tabs are not visible (step preconditions are on the roadmap).
- **Livewire morph moved the element**: the popover re-anchors on `livewire:navigated`, morph hooks and resize. If it drifts, make sure the target element keeps a stable `data-tour` / `wire:key` so it survives the morph.
- **No assets**: run `php artisan filament:assets` after installing or upgrading.
- **Custom theme**: the popover uses Filament's CSS variables (`--primary-*`, `--gray-*`). Override `.driver-popover.io-popover` in your theme if needed.

## Testing

```bash
composer test          # Pest
composer analyse       # PHPStan (level 5)
composer format        # Pint
npm run build          # rebuild resources/dist (committed; consumers do not need npm)
npm run test:browser   # optional Playwright smoke tests of the bundle (see tests/browser)
```

## Roadmap

- **v0.2**: step preconditions (open a modal/tab before a step), `wire:click` interception.
- **v0.3**: analytics (views, completions, per-step drop-off, target-not-found reports).
- **v0.4**: multi-language step content.
- **v1.0**: hint/beacon mode, audience segments, stability guarantees.

## Credits

- [Alicia / ArzCode](https://github.com/buzkall)
- [Driver.js](https://driverjs.com) by Kamran Ahmed

## License

MIT. See [LICENSE.md](LICENSE.md).
