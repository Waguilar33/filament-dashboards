<?php

declare(strict_types=1);

return [
    'resource' => [
        'label' => 'dashboard',
        'plural_label' => 'Dashboards',
    ],
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'show_in_navigation' => 'Add to navigation',
        'navigation_icon' => 'Navigation icon',
        'navigation_label' => 'Navigation label',
        'navigation_label_helper' => 'Leave empty to use the dashboard name.',
        'navigation_group' => 'Navigation group',
        'navigation_position' => 'Navigation position',
        'kind' => 'Kind',
        'owner' => 'Owner',
        'widget' => 'Widget',
        'width' => 'Width',
        'shares' => 'Shared with',
        'shareable_type' => 'Type',
        'shareable' => 'Who',
        'level' => 'Access',
    ],
    'kinds' => [
        'mine' => 'Mine',
        'shared' => 'Shared with me',
        'system' => 'System',
    ],
    'navigation_positions' => [
        'start' => 'Start of group',
        'end' => 'End of group',
    ],
    'share_levels' => [
        'read' => 'Read',
        'edit' => 'Edit',
    ],
    'shareable_types' => [
        'user' => 'User',
        'role' => 'Role',
    ],
    'widths' => [
        'quarter' => 'Quarter',
        'third' => 'Third',
        'half' => 'Half',
        'two_thirds' => 'Two thirds',
        'full' => 'Full',
    ],
    'actions' => [
        'add_widget' => 'Add widget',
        'share' => 'Share',
        'settings' => 'Settings',
        'clone' => 'Clone',
        'set_home' => 'Set as home',
        'delete' => 'Delete',
        'edit_layout' => 'Edit layout',
        'done' => 'Done',
        'remove' => 'Remove',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'drag' => 'Drag to move',
        'previous' => 'Previous',
        'next' => 'Next',
        'resize' => 'Resize',
    ],
    'modals' => [
        'add_widget' => [
            'heading' => 'Add widget',
            'description' => 'Widgets are cards that show figures, charts or lists.',
        ],
        'share' => [
            'heading' => 'Share',
            'description' => 'Choose who can see this dashboard and who can edit it. Nobody but you can delete it.',
            'add' => 'Add',
        ],
        'clone' => [
            'heading' => 'Clone dashboard',
            'description' => 'Make an editable copy of this dashboard that only you can see.',
            'name' => 'Copy of :name',
        ],
    ],
    'picker' => [
        'quick' => [
            'heading' => 'Quick widgets',
            'description' => 'Ready-made widgets you can add in one click.',
        ],
    ],
    'notifications' => [
        'home_set' => 'This dashboard is now your home page.',
        'read_only' => 'You can view this dashboard. Clone it to make your own copy.',
    ],
    'empty' => [
        'heading' => 'No widgets yet',
        'description' => 'Add widgets to start building this dashboard.',
    ],
    'validation' => [
        'slug_taken' => 'Another dashboard already uses this slug.',
    ],
];
