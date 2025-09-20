<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for the AZ TV platform
    |
    */

    'antivirus' => [
        'enabled' => env('ANTIVIRUS_ENABLED', false),
        'scanner' => env('ANTIVIRUS_SCANNER', 'clamav'),

        // ClamAV configuration
        'clamscan_path' => env('CLAMSCAN_PATH', '/usr/bin/clamscan'),
        'clamd_socket' => env('CLAMD_SOCKET', '/var/run/clamav/clamd.ctl'),
        'freshclam_path' => env('FRESHCLAM_PATH', '/usr/bin/freshclam'),
        'database_path' => env('CLAMAV_DATABASE_PATH', '/var/lib/clamav'),
        'scan_timeout' => env('ANTIVIRUS_SCAN_TIMEOUT', 300), // 5 minutes

        // Scan behavior
        'scan_on_upload' => env('ANTIVIRUS_SCAN_ON_UPLOAD', true),
        'quarantine_infected' => env('ANTIVIRUS_QUARANTINE', true),
        'delete_infected' => env('ANTIVIRUS_DELETE_INFECTED', false),
        'queue' => env('ANTIVIRUS_QUEUE', 'virus-scanning'),

        // Update settings
        'auto_update_definitions' => env('ANTIVIRUS_AUTO_UPDATE', true),
        'update_frequency' => env('ANTIVIRUS_UPDATE_FREQUENCY', 'daily'), // daily, weekly
    ],

    'file_validation' => [
        'enabled' => env('FILE_VALIDATION_ENABLED', true),
        'strict_mode' => env('FILE_VALIDATION_STRICT', false),
        'log_all_attempts' => env('FILE_VALIDATION_LOG_ALL', true),
        'log_retention_days' => env('FILE_VALIDATION_LOG_RETENTION', 90),

        // Security thresholds
        'max_failed_attempts_per_ip' => env('FILE_VALIDATION_MAX_FAILS_IP', 10),
        'max_failed_attempts_per_hour' => env('FILE_VALIDATION_MAX_FAILS_HOUR', 50),
        'suspicious_ip_threshold' => env('FILE_VALIDATION_SUSPICIOUS_THRESHOLD', 3),

        // Content scanning
        'scan_file_headers' => env('FILE_VALIDATION_SCAN_HEADERS', true),
        'scan_file_content' => env('FILE_VALIDATION_SCAN_CONTENT', true),
        'max_content_scan_size' => env('FILE_VALIDATION_MAX_SCAN_SIZE', 8192), // bytes
    ],

    'upload_security' => [
        'temp_file_retention' => env('UPLOAD_TEMP_RETENTION', 3600), // 1 hour
        'max_upload_rate_per_minute' => env('UPLOAD_MAX_RATE', 10),
        'quarantine_path' => env('UPLOAD_QUARANTINE_PATH', 'quarantine'),
    ],
];