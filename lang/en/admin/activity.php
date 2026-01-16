<?php

/**
 * Contains all of the translation strings for admin activity log events.
 */
return [
    'title' => 'Admin Activity',
    'model_label' => 'Admin Activity',
    'model_label_plural' => 'Admin Activities',
    'event' => 'Event',
    'actor' => 'Actor',
    'subject' => 'Subject',
    'timestamp' => 'Timestamp',
    'properties' => 'Properties',
    'metadata' => 'Metadata',
    'system' => 'System',

    // Server events
    'server' => [
        'created' => 'Created server <b>:name</b>',
        'updated' => 'Updated server <b>:name</b>',
        'deleted' => 'Deleted server <b>:name</b>',
        'suspended' => 'Suspended server <b>:name</b>',
        'unsuspended' => 'Unsuspended server <b>:name</b>',
        'reinstalled' => 'Reinstalled server <b>:name</b>',
        'transferred' => 'Transferred server <b>:name</b> to node <b>:node</b>',
        'variables' => [
            'updated' => 'Updated server <b>:name</b> variables',
        ],
        'limits' => [
            'updated' => 'Updated server <b>:name</b> limits',
        ],
        'allocation' => [
            'added' => 'Added allocation <b>:allocation</b> to server <b>:name</b>',
            'removed' => 'Removed allocation <b>:allocation</b> from server <b>:name</b>',
            'primary' => 'Set <b>:allocation</b> as primary allocation for server <b>:name</b>',
        ],
        'egg' => [
            'changed' => 'Changed egg for server <b>:name</b> to <b>:egg</b>',
        ],
    ],

    // User events
    'user' => [
        'created' => 'Created user <b>:username</b>',
        'updated' => 'Updated user <b>:username</b>',
        'deleted' => 'Deleted user <b>:username</b>',
        'email' => [
            'changed' => 'Changed email for user <b>:username</b> from <b>:old</b> to <b>:new</b>',
        ],
        'password' => [
            'changed' => 'Changed password for user <b>:username</b>',
        ],
        'roles' => [
            'added' => 'Added role(s) <b>:roles</b> to user <b>:username</b>',
            'removed' => 'Removed role(s) <b>:roles</b> from user <b>:username</b>',
            'updated' => 'Updated roles for user <b>:username</b>',
        ],
    ],

    // Node events
    'node' => [
        'created' => 'Created node <b>:name</b>',
        'updated' => 'Updated node <b>:name</b>',
        'deleted' => 'Deleted node <b>:name</b>',
    ],

    // Egg events
    'egg' => [
        'created' => 'Created egg <b>:name</b>',
        'updated' => 'Updated egg <b>:name</b>',
        'deleted' => 'Deleted egg <b>:name</b>',
        'imported' => 'Imported egg <b>:name</b>',
        'exported' => 'Exported egg <b>:name</b>',
    ],

    // Database host events
    'database-host' => [
        'created' => 'Created database host <b>:name</b>',
        'updated' => 'Updated database host <b>:name</b>',
        'deleted' => 'Deleted database host <b>:name</b>',
    ],

    // Role events
    'role' => [
        'created' => 'Created role <b>:name</b>',
        'updated' => 'Updated role <b>:name</b>',
        'deleted' => 'Deleted role <b>:name</b>',
        'permissions' => [
            'updated' => 'Updated permissions for role <b>:name</b>',
        ],
    ],

    // Settings events
    'settings' => [
        'updated' => 'Updated panel settings',
    ],
];
