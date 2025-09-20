<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for media file processing in the AZ TV system
    |
    */

    'compression' => [
        'video_bitrate' => env('MEDIA_VIDEO_BITRATE', 1000), // kbps
        'audio_bitrate' => env('MEDIA_AUDIO_BITRATE', 128), // kbps
    ],

    'thumbnail' => [
        'width' => env('MEDIA_THUMBNAIL_WIDTH', 300),
        'height' => env('MEDIA_THUMBNAIL_HEIGHT', 200),
        'quality' => env('MEDIA_THUMBNAIL_QUALITY', 80),
    ],

    'processing' => [
        'timeout' => env('MEDIA_PROCESSING_TIMEOUT', 1800), // 30 minutes
        'max_retries' => env('MEDIA_PROCESSING_MAX_RETRIES', 3),
        'queue' => env('MEDIA_PROCESSING_QUEUE', 'video-processing'),
    ],

    'formats' => [
        'video' => [
            'output_format' => 'mp4',
            'codec' => 'x264',
        ],
        'thumbnail' => [
            'format' => 'jpg',
            'time' => env('MEDIA_THUMBNAIL_TIME', 1), // seconds
        ],
    ],

    'validation' => [
        'enabled' => env('MEDIA_VALIDATION_ENABLED', true),
        'strict_mode' => env('MEDIA_VALIDATION_STRICT', false),
        'log_all_attempts' => env('MEDIA_VALIDATION_LOG_ALL', true),
    ],

    'antivirus' => [
        'enabled' => env('ANTIVIRUS_ENABLED', false),
        'scanner' => env('ANTIVIRUS_SCANNER', 'clamav'), // clamav, virustotal
        'scan_on_upload' => env('ANTIVIRUS_SCAN_ON_UPLOAD', true),
        'quarantine_infected' => env('ANTIVIRUS_QUARANTINE', true),
        'queue' => env('ANTIVIRUS_QUEUE', 'virus-scanning'),
    ],
];