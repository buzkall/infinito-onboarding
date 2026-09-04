# Contributing

Thanks for considering a contribution. Please read [CLAUDE.md](CLAUDE.md) first: it is the contract for conventions in this repository (Filament 5 rules, schema, targeting priority, JS rules).

## Setup

```bash
git clone https://github.com/buzkall/infinito-onboarding.git
cd infinito-onboarding
composer install
npm install
```

## Quality gates

Every pull request must keep these green:

```bash
vendor/bin/pest            # tests
vendor/bin/pint --test     # code style
vendor/bin/phpstan analyse # static analysis, level 5
```

If you change anything under `resources/js` or `resources/css`, run `npm run build` and commit the `resources/dist` output: consumers must not need npm. The optional browser smoke tests (`npm run test:browser`, needs Playwright) are a good way to check the bundle in a real Chromium.

## Pull requests

- One topic per PR, with tests. Resolver branches in particular need a failing-if-removed test each.
- Follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `chore:`, `test:`).
- Update `CHANGELOG.md` under *Unreleased*.
- Do not target raw `.fi-*` classes for anything functional; they change between Filament releases.

## Reporting bugs

Use the bug report issue template and include Filament, Livewire, Laravel and PHP versions, plus the tour JSON (`php artisan onboarding:export <key>`) when the problem is about a specific tour.
