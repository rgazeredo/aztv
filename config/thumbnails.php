<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Thumbnail Sizes
    |--------------------------------------------------------------------------
    |
    | Define the available thumbnail sizes and their dimensions.
    | Each size will be generated automatically for supported media types.
    |
    */
    'sizes' => [
        'small' => [
            'width' => 150,
            'height' => 150,
            'quality' => 85,
        ],
        'medium' => [
            'width' => 300,
            'height' => 300,
            'quality' => 90,
        ],
        'large' => [
            'width' => 600,
            'height' => 600,
            'quality' => 95,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Size
    |--------------------------------------------------------------------------
    |
    | The default thumbnail size to use when no specific size is requested.
    |
    */
    'default_size' => 'medium',

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for thumbnail storage location and organization.
    |
    */
    'storage' => [
        'disk' => 'public',
        'directory' => 'thumbnails',
        'subdirectory_pattern' => '{tenant_id}/{media_file_id}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    |
    | Settings for image thumbnail generation.
    |
    */
    'image' => [
        'driver' => 'gd', // 'gd' or 'imagick'
        'format' => 'jpg',
        'quality' => 90,
        'maintain_aspect_ratio' => true,
        'background_color' => '#ffffff',
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Processing
    |--------------------------------------------------------------------------
    |
    | Settings for video thumbnail generation using FFmpeg.
    |
    */
    'video' => [
        'enabled' => true,
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
        'default_timestamp' => '00:00:01',
        'format' => 'jpg',
        'quality' => 90,
        'fallback_timestamps' => [
            '00:00:05',
            '00:00:10',
            '00:00:30',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Options
    |--------------------------------------------------------------------------
    |
    | General processing options for thumbnail generation.
    |
    */
    'processing' => [
        'queue' => env('THUMBNAIL_QUEUE', 'media'),
        'timeout' => 300, // 5 minutes
        'retry_attempts' => 3,
        'retry_delay' => 60, // seconds
        'auto_generate' => true,
        'generate_on_upload' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatic cleanup of thumbnails.
    |
    */
    'cleanup' => [
        'auto_delete' => true,
        'orphaned_cleanup_enabled' => true,
        'orphaned_cleanup_schedule' => 'daily',
        'max_age_days' => null, // null = never expire
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Settings for thumbnail caching and optimization.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'headers' => [
            'Cache-Control' => 'public, max-age=31536000', // 1 year
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported File Types
    |--------------------------------------------------------------------------
    |
    | MIME types that support thumbnail generation.
    |
    */
    'supported_types' => [
        'image' => [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff',
        ],
        'video' => [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/m4v',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | How to handle errors during thumbnail generation.
    |
    */
    'error_handling' => [
        'log_errors' => true,
        'fail_silently' => false,
        'default_thumbnail' => null, // Path to default thumbnail image
        'notification_channels' => [], // Channels to notify on errors
    ],
];