<?php

return [

    
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sonar' => [
        'host'                => env('SONAR_HOST', 'http://sonarqube:9000'),
        'token'               => env('SONAR_TOKEN'),
        'scanner_bin'         => env('SONAR_SCANNER_BIN', '/opt/sonar-scanner/bin/sonar-scanner'),
    ],


    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],


    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
        'api_key' => env('OLLAMA_API_KEY'),
        'model' => env('OLLAMA_MODEL', 'gemma4:31b-cloud'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    'ai_hr' => [
        'conduct_enabled' => filter_var(env('HR_CONDUCT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'features' => [
        'senior_program_card' => filter_var(env('SENIOR_PROGRAM_CARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'adaptive_test_continue_bar' => filter_var(env('ADAPTIVE_TEST_CONTINUE_BAR_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'lesson' => [
        'lenient_review' => filter_var(env('LESSON_LENIENT_REVIEW', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
