<?php

return [
    'title' => 'Notifications',
    'mark_all_read' => 'Mark All as Read',
    'mark_read' => 'Mark as Read',
    'confirm' => 'Confirm',
    'confirmed' => 'Confirmed',
    'no_notifications' => 'No notifications',
    'no_notifications_desc' => 'You have no notifications at the moment.',
    'type' => 'Type',
    'message' => 'Message',
    'date' => 'Date',
    'actions' => 'Actions',
    'types' => [
        'info' => 'Info',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'functional' => 'Action Required'
    ],
    // Notification messages
    'build_started_title' => 'Construction Started',
    'build_started_message' => 'Construction of :objectType started. Will be ready in :time.',
    'build_completed_title' => 'Construction Completed',
    'build_completed_message' => ':objectType is ready for use.',
    'upgrade_started_title' => 'Upgrade Started',
    'upgrade_started_message' => 'Upgrade of :objectType started. Will be ready in :time.',
    'upgrade_completed_title' => 'Upgrade Completed',
    'upgrade_completed_message' => ':objectType has been upgraded to level :level.',
    'population_increase_title' => 'Population Increased',
    'population_increase_message' => 'Your population increased by :count people.',
    'population_decrease_title' => 'Population Decrease',
    'population_decrease_message' => 'Due to insufficient hospital capacity, :count people have died.',
    'production_cancelled_title' => 'Production Cancelled',
    'production_cancelled_message' => 'Production cancelled for :count workers at level :level due to insufficient people.',
    'production_overassigned_title' => 'Production Over-assigned',
    'production_overassigned_message' => 'There are :assigned workers assigned but only :available available at level :level.',
];
