<?php
/**
 * Admin Role Menu Configuration
 * Returns menu structure for Admin role
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
                'active' => false, // Don't hardcode active - let JavaScript manage it
                // Overview is always visible
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
                // Scheduling Module: visible with either view or manage privileges
                'permissions' => ['view_schedules', 'manage_schedules', 'assign_schedules'],
            ],
            [
                'label' => 'Subjects',
                'icon' => 'bi bi-book',
                'href' => '#curriculum',
                'onclick' => "if(typeof showTab === 'function') { showTab('curriculum', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                // Academic Module (subjects)
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
                // Rooms Module
                'permissions' => ['view_rooms', 'manage_rooms', 'approve_room_requests'],
            ],
            [
                'label' => 'Users',
                'icon' => 'bi bi-people',
                'href' => '#roles',
                'onclick' => "if(typeof showTab === 'function') { showTab('roles', this); } return false;",
                'data' => ['bs-toggle' => 'tab'],
                // Users Module
                'permissions' => ['view_users', 'manage_users'],
            ],
        ]
    ],
];

