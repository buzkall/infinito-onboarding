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
