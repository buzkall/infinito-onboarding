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
