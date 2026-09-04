# Changelog

All notable changes to `arzcode/infinito-onboarding` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
