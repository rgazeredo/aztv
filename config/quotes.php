<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Quote Display Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for quote display behavior and rotation settings.
    |
    */

    'default_display_duration' => env('QUOTE_DEFAULT_DURATION', 30), // seconds

    'min_display_duration' => 10, // seconds
    'max_display_duration' => 300, // seconds (5 minutes)

    /*
    |--------------------------------------------------------------------------
    | Rotation Settings
    |--------------------------------------------------------------------------
    |
    | Default rotation behavior and timing configurations.
    |
    */

    'rotation' => [
        'default_mode' => 'sequential', // sequential, random, newest, oldest
        'default_interval' => 30, // seconds between quote changes
        'min_interval' => 10,
        'max_interval' => 600, // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache configuration for quote retrieval and rotation sequences.
    |
    */

    'cache' => [
        'ttl' => 3600, // 1 hour in seconds
        'key_prefix' => 'quotes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quote Categories
    |--------------------------------------------------------------------------
    |
    | Available categories for quotes with their display names and descriptions.
    |
    */

    'categories' => [
        'motivacional' => [
            'name' => 'Motivacional',
            'description' => 'Frases para inspirar e motivar',
            'icon' => '💪',
            'color' => '#3498db',
        ],
        'inspiracional' => [
            'name' => 'Inspiracional',
            'description' => 'Reflexões inspiradoras',
            'icon' => '🌟',
            'color' => '#e74c3c',
        ],
        'empresarial' => [
            'name' => 'Empresarial',
            'description' => 'Frases sobre negócios e empreendedorismo',
            'icon' => '💼',
            'color' => '#2ecc71',
        ],
        'sucesso' => [
            'name' => 'Sucesso',
            'description' => 'Sobre conquistas e realizações',
            'icon' => '🏆',
            'color' => '#f39c12',
        ],
        'liderança' => [
            'name' => 'Liderança',
            'description' => 'Sobre liderança e gestão',
            'icon' => '👑',
            'color' => '#9b59b6',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Effects
    |--------------------------------------------------------------------------
    |
    | Available transition effects for quote display.
    |
    */

    'effects' => [
        'fade' => [
            'name' => 'Fade',
            'duration' => 1000, // milliseconds
        ],
        'slide' => [
            'name' => 'Slide',
            'duration' => 800,
        ],
        'zoom' => [
            'name' => 'Zoom',
            'duration' => 600,
        ],
        'none' => [
            'name' => 'None',
            'duration' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Text Constraints
    |--------------------------------------------------------------------------
    |
    | Validation constraints for quote text and author fields.
    |
    */

    'validation' => [
        'text' => [
            'max_length' => 500,
            'min_length' => 10,
        ],
        'author' => [
            'max_length' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for quote API endpoints and player authentication.
    |
    */

    'api' => [
        'rate_limit' => 60, // requests per minute per player
        'require_authentication' => true,
        'default_response_format' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    |
    | Configuration for quote usage statistics and analytics.
    |
    */

    'statistics' => [
        'track_views' => true,
        'track_player_preferences' => true,
        'retention_days' => 90,
    ],
];