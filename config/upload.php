<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for file uploads in the AZ TV system
    |
    */

    'default_disk' => env('UPLOAD_DISK', 'public'),

    'allowed_mime_types' => [
        'video' => [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/quicktime',
            'video/x-msvideo',
        ],
        'image' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
            'image/jpg',
        ],
        'audio' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
            'audio/x-wav',
        ],
    ],

    'storage_limits' => [
        'basic' => [
            'total' => 1024 * 1024 * 1024, // 1GB
            'per_file' => 100 * 1024 * 1024, // 100MB
        ],
        'professional' => [
            'total' => 5 * 1024 * 1024 * 1024, // 5GB
            'per_file' => 500 * 1024 * 1024, // 500MB
        ],
        'enterprise' => [
            'total' => 20 * 1024 * 1024 * 1024, // 20GB
            'per_file' => 2 * 1024 * 1024 * 1024, // 2GB
        ],
    ],

    'thumbnails' => [
        'enabled' => env('UPLOAD_THUMBNAILS_ENABLED', true),
        'width' => env('UPLOAD_THUMBNAIL_WIDTH', 300),
        'height' => env('UPLOAD_THUMBNAIL_HEIGHT', 200),
        'quality' => env('UPLOAD_THUMBNAIL_QUALITY', 80),
    ],

    'video_processing' => [
        'enabled' => env('UPLOAD_VIDEO_PROCESSING_ENABLED', true),
        'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
        'thumbnail_time' => env('VIDEO_THUMBNAIL_TIME', 1), // seconds
    ],

    'temp_directory' => storage_path('app/temp'),

    'max_files_per_upload' => env('UPLOAD_MAX_FILES', 10),

    'chunk_upload' => [
        'enabled' => env('UPLOAD_CHUNK_ENABLED', false),
        'chunk_size' => env('UPLOAD_CHUNK_SIZE', 1024 * 1024), // 1MB
    ],
];