<?php
/**
 * Admin Support Role Menu Configuration
 * Returns menu structure for Admin Support role
 */

return [
    [
        'label' => 'Dashboard',
        'items' => [
            [
                'label' => 'Overview',
                'icon' => 'bi bi-speedometer2',
                // Go to Admin Support overview tab
                'href' => '/views/admin_support/index.php?tab=overview',
                'active' => true,
            ]
        ]
    ],
    [
        'label' => 'Users Module',
        'items' => [
            [
                'label' => 'User Management',
                'icon' => 'bi bi-people',
                // Go directly to Admin Support index with users tab
                'href' => '/views/admin_support/index.php?tab=users',
                // Users Module (view/manage)
                'permissions' => ['view_users', 'manage_users'],
            ],
            [
                'label' => 'Manage Roles',
                'icon' => 'bi bi-shield-lock',
                // Go directly to Admin Support index with roles tab
                'href' => '/views/admin_support/index.php?tab=roles',
                // Manage Roles
                'permissions' => ['manage_roles'],
            ],
        ]
    ],
];

