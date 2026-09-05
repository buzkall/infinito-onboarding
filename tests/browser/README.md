# Browser smoke tests (optional)

These scripts exercise the built bundle in a real headless Chromium through
Playwright. They are **not** part of the Pest suite or CI; run them by hand
after changing anything under `resources/js` or `resources/css`.

```bash
npm run build
npx --yes playwright install chromium   # once
node tests/browser/tour.smoke.js
```

`tour.smoke.js` loads `tour.html` (Livewire's real bundle + our dist files),
drives a four-step tour (one `data-tour` target, one target that appears late,
one missing target that must be skipped with a console warning, one centred
step), completes it, then re-runs it and dismisses it with Escape and with the
close button. It prints the DOM/event state after each action; every line
should show the expected title, progress text and events.

`recorder.smoke.js` loads `recorder.html` and checks record mode: selector
scoring for a `data-tour` target (green), an element id (green), a
`wire:key` ancestor (amber) and a generated CSS path (red, with the
`->tourTarget()` hint), then walks through pick → hover → click → fill →
add step, adds a second (red) step, previews the result with Driver.js and
dismisses it with Escape.

```bash
node tests/browser/recorder.smoke.js
```

`preconditions.smoke.js` loads `preconditions.html`: step 1 advances when
its highlighted button is clicked (`advance_on_click`), step 2 lives inside a
hidden "modal" that a `before` click action opens first, step 3 can never be
found and must be skipped in both directions, step 4 completes the tour.

```bash
node tests/browser/preconditions.smoke.js
```

`hints.smoke.js` loads `hints.html` (hint mode): two beacons are mounted next
to their targets, one target is missing (skipped with a warning) and one
hint is already dismissed; opening a beacon shows the popover with "Got it",
dismissing removes the beacon, Escape keeps it, and dismissing the last one
empties the page.

```bash
node tests/browser/hints.smoke.js
```

## Regenerating the README screenshots

The repository ships a small Testbench workbench (`workbench/`, `testbench.yaml`)
with a demo "Orders" page whose components carry `->tourTarget()` keys and a
seeder that creates a guided tour, a hint tour, a changelog and analytics events.

```bash
composer workbench:reset          # migrate, seed, publish Filament assets
composer workbench:serve          # http://127.0.0.1:8000/admin (alicia@example.com / password)
node tests/browser/screenshots.js # writes docs/screenshots/*.png
```
