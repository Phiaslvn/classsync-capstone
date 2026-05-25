<?php
/**
 * Moderator Role Menu Configuration
 * Same navigation as Department Admin (assistant head): Academic and Resources.
 * Permission checks still gate each item; Moderator role_permissions match Admin in DB seed.
 */

return [
    [
        'label' => 'Dashboard',
        'items' => [
            [
                'label' => 'Overview',
                'icon' => 'bi bi-speedometer2',
                'href' => '#overview',
                'onclick' => "if(typeof showTab === 'function') { showTab('overview', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'active' => false,
            ],
            [
                'label' => 'Profile',
                'icon' => 'bi bi-person-gear',
                'href' => '#profile',
                'onclick' => "if(typeof showTab === 'function') { showTab('profile', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'active' => false,
            ],
        ]
    ],
    [
        'label' => 'Academic',
        'items' => [
            [
                'label' => 'Schedules',
                'icon' => 'bi bi-calendar-week',
                'href' => '#schedule',
                'onclick' => "if(typeof showTab === 'function') { showTab('schedule', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'permissions' => ['view_schedules', 'manage_schedules', 'assign_schedules'],
            ],
            [
                'label' => 'Subjects',
                'icon' => 'bi bi-book',
                'href' => '#curriculum',
                'onclick' => "if(typeof showTab === 'function') { showTab('curriculum', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'permissions' => ['manage_subjects', 'manage_curriculum'],
            ],
            [
                'label' => 'Course',
                'icon' => 'bi bi-diagram-3',
                'href' => '#course_management',
                'onclick' => "if(typeof showTab === 'function') { showTab('course_management', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'permissions' => ['manage_programs'],
            ],
        ]
    ],
    [
        'label' => 'Resources',
        'items' => [
            [
                'label' => 'Rooms',
                'icon' => 'bi bi-building',
                'href' => '#room_requests',
                'onclick' => "if(typeof showTab === 'function') { showTab('room_requests', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'permissions' => ['view_rooms', 'manage_rooms', 'approve_room_requests'],
            ],
            [
                'label' => 'Users',
                'icon' => 'bi bi-people',
                'href' => '#roles',
                'onclick' => "if(typeof showTab === 'function') { showTab('roles', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                'permissions' => ['view_users', 'manage_users'],
            ],
        ]
    ],
];
