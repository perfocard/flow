<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Status Configuration
    |--------------------------------------------------------------------------
    |
    | Defines the table and model used for storing statuses. The 'nova_navigation'
    | option controls whether the status resource appears in Nova navigation.
    */
    'status' => [
        // The database table for statuses
        'table' => 'statuses',
        // The Eloquent model class for statuses
        'model' => \Perfocard\Flow\Models\Status::class,

        'policy' => \Perfocard\Flow\Policies\StatusPolicy::class,

        // Show status resource in Nova navigation
        'nova_navigation' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for file compression, including disk configuration and timeout.
    | 'remote' and 'temp' specify the disks used for storing compressed and temporary files.
    | 'timeout' sets the maximum allowed compression time (in minutes).
    */
    'compression' => [
        'disk' => [
            // WARNING: Do NOT use the same disk for both 'remote' and 'temp'.
            // If both point to the same disk the temporary archive will be removed
            // during the cleanup step, leaving no archive available. Use distinct
            // disks for 'temp' and 'remote'.

            // Disk for storing compressed files
            'remote' => env('FLOW_COMPRESSION_DISK_REMOTE', 's3'),

            // Disk for storing temporary files during compression
            'temp' => env('FLOW_COMPRESSION_DISK_TEMP', 'local'),

        ],

        // Compression timeout in minutes
        'timeout' => env('FLOW_COMPRESSION_TIMEOUT', 60 * 24 * 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for purging extracted status payloads. 'timeout' sets the minimum
    | time (in minutes) after extraction before payloads are purged.
    */
    'purge' => [
        // Purge timeout in minutes after extraction
        'timeout' => env('FLOW_PURGE_TIMEOUT', 60 * 24 * 2),
    ],

    // 'probes' => [
    //     \App\Models\Payment::class => [
    //         'probe_model' => \App\Models\Payment\Probe::class,
    //         'trigger_statuses' => [
    //             \App\Models\PaymentStatus::PENDING,
    //         ],
    //         'grace' => 300, // 5 minutes
    //         'batch' => 200,
    //     ],
    // ],
];
