<?php

return [

    'modes' => [
        'tour' => 'Guided tour',
        'changelog' => 'Changelog',
    ],

    'target_types' => [
        'data_tour' => 'Tour target (data-tour)',
        'css' => 'CSS selector',
        'none' => 'No target (centred)',
    ],

    'placements' => [
        'auto' => 'Auto',
        'top' => 'Top',
        'right' => 'Right',
        'bottom' => 'Bottom',
        'left' => 'Left',
    ],

    'resource' => [
        'label' => 'Tour',
        'plural_label' => 'Onboarding tours',
        'sections' => [
            'basics' => 'Tour',
            'publishing' => 'Publishing',
            'audience' => 'Audience',
            'audience_help' => 'Leave everything empty to show the tour to every user. Each filled criterion must be satisfied.',
        ],
        'fields' => [
            'name' => 'Name',
            'key' => 'Key',
            'key_help' => 'Unique identifier used in code, exports and the preview URL.',
            'description' => 'Description',
            'mode' => 'Mode',
            'version' => 'Version',
            'version_help' => 'Bump this to show the tour again to users who already saw it.',
            'route_pattern' => 'Route pattern',
            'route_pattern_help' => 'Path the tour runs on, e.g. admin/orders*. Wildcards allowed, comma-separate several. Empty = every page.',
            'is_active' => 'Active',
            'is_published' => 'Published',
            'published_at' => 'Published at',
            'starts_at' => 'Starts at',
            'ends_at' => 'Ends at',
            'sort' => 'Sort',
            'audience_roles' => 'Roles',
            'audience_permissions' => 'Permissions',
            'tenant_id' => 'Tenant',
            'tenant_id_help' => 'Leave empty to show in every tenant.',
            'steps_count' => 'Steps',
            'completions_count' => 'Seen by',
        ],
        'actions' => [
            'preview' => 'Preview',
            'reset_seen_state' => 'Reset seen-state',
            'reset_seen_state_confirm' => 'Every user will see this tour again on their next visit.',
            'reset_seen_state_done' => 'Seen-state reset for :count user(s).',
        ],
        'steps' => [
            'title' => 'Steps',
            'fields' => [
                'title' => 'Title',
                'target_type' => 'Target type',
                'target' => 'Target',
                'target_data_tour' => 'Tour target key',
                'target_data_tour_help' => 'The key passed to ->tourTarget(\'key\') on the Filament component.',
                'target_css' => 'CSS selector',
                'target_css_help' => 'Fragile: prefer a tour target key. Must match exactly one element.',
                'body' => 'Body',
                'placement' => 'Placement',
            ],
        ],
    ],

    'overlay' => [
        'next' => 'Next',
        'previous' => 'Back',
        'done' => 'Done',
        'close' => 'Close',
        'progress' => 'Step :current of :total',
    ],

];
