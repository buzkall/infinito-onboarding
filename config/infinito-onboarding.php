<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Every table the package creates can be renamed here. Change these before
    | running the migrations for the first time.
    |
    */

    'table_names' => [
        'tours' => 'onboarding_tours',
        'tour_steps' => 'onboarding_tour_steps',
        'tour_completions' => 'onboarding_tour_completions',
        'tour_events' => 'onboarding_tour_events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query parameters
    |--------------------------------------------------------------------------
    |
    | `preview` forces a tour to run for the current user regardless of
    | seen-state (`?onboarding-preview=1` for the tour that would resolve, or
    | `?onboarding-preview=<tour-key>` for a specific one). `record` activates
    | the visual authoring mode (`?onboarding-record=<tour-key>`). Both are only
    | honoured for users that pass the plugin's authorize() closure.
    |
    */

    'query_parameters' => [
        'preview' => 'onboarding-preview',
        'record' => 'onboarding-record',
    ],

    /*
    |--------------------------------------------------------------------------
    | Export path
    |--------------------------------------------------------------------------
    |
    | Where `onboarding:export` writes tour JSON files and where
    | `onboarding:import` reads them from.
    |
    */

    'export_path' => database_path('tours'),

    /*
    |--------------------------------------------------------------------------
    | Audience segments
    |--------------------------------------------------------------------------
    |
    | Named, reusable audiences that tours reference through
    | `audience.segments`. Each is either criteria (roles / permissions /
    | users) or a callable receiving the user. Closures can also be registered
    | from code with InfinitoOnboardingPlugin::make()->segment('beta', fn ($user) => …).
    |
    */

    'segments' => [
        // 'admins' => ['roles' => ['admin']],
        // 'beta' => fn (\Illuminate\Contracts\Auth\Authenticatable $user) => $user->is_beta,
    ],

    'segment_labels' => [
        // 'beta' => 'Beta testers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Locales tour content can be authored in, e.g. ['en', 'es']. The first
    | one is the default and lives in the base columns; the others are stored
    | in the `translations` JSON column and edited through per-locale tabs in
    | the resource. Leave empty to use the app locale only. The overlay picks
    | the current app locale, then `fallback_locale`, then the default.
    |
    */

    'locales' => [],

    'fallback_locale' => null,

    'locale_labels' => [
        // 'es' => 'Español',
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Views, highlighted steps, completions, dismissals and missing targets
    | are recorded in the events table and shown on the tour's edit page.
    | `onboarding:prune-events` deletes events older than `prune_after_days`.
    |
    */

    'analytics' => [
        'enabled' => true,
        'prune_after_days' => 90,
    ],

];
