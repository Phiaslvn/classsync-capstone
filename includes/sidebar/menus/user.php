<?php
/**
 * User/Instructor Role Menu Configuration
 * Returns menu structure for User/Instructor role
 * 
 * Instructors have limited access - they can only:
 * - View their own schedules
 * - Generate schedule reports (print/download)
 * - Access profile settings
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
                'active' => true,
                // Always visible
            ]
        ]
    ],
    [
        'label' => 'Scheduling Module',
        'items' => [
            [
                'label' => 'My Schedules',
                'icon' => 'bi bi-calendar-week',
                'href' => '#schedule',
                'onclick' => "if(typeof showTab === 'function') { showTab('schedule', this); } return false;",
                // Instructors can see schedules when they have view permissions
                'permissions' => ['view_schedules', 'view_own_schedule'],
            ]
        ]
    ]
];

