<?php

return [
    // Maximum size of a single uploaded attachment, in kilobytes.
    'max_upload_kb' => (int) env('TASKFLOW_MAX_UPLOAD_KB', 20480),

    // Total storage each workspace may use for uploaded files, in megabytes (0 = unlimited).
    'workspace_storage_mb' => (int) env('TASKFLOW_WORKSPACE_STORAGE_MB', 2048),

    // File types people may upload. Anything else (programs, scripts, web pages…) is refused.
    'allowed_upload_extensions' => [
        // documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv', 'md', 'pages', 'numbers', 'key',
        // images
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'avif', 'heic', 'heif', 'svg', 'tif', 'tiff',
        // design
        'psd', 'ai', 'eps', 'sketch', 'fig', 'xd', 'indd',
        // audio and video
        'mp3', 'wav', 'm4a', 'aac', 'ogg', 'mp4', 'mov', 'webm', 'avi', 'mkv',
        // archives and fonts
        'zip', '7z', 'rar', 'gz', 'tar', 'ttf', 'otf', 'woff', 'woff2',
    ],

    // Requests allowed per minute for one signed-in person (or one address when signed out).
    'requests_per_minute' => (int) env('TASKFLOW_REQUESTS_PER_MINUTE', 300),

    // Load the demo workspace on first deploy (only when the users table is empty).
    // Shown in front of currency custom fields.
    'currency_symbol' => env('TASKFLOW_CURRENCY_SYMBOL', '₱'),

    // Timesheets group and show time in this timezone (times are stored in UTC).
    'timezone' => env('TASKFLOW_TIMEZONE', 'Asia/Manila'),

    // Where browsers connect for live updates (the public address of the Reverb service).
    'realtime' => [
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => env('REVERB_PUBLIC_PORT', 443),
        'scheme' => env('REVERB_PUBLIC_SCHEME', 'https'),
    ],

    // New sign-ups confirm their email before using the app. On by default only when a real
    // mailer is configured (with MAIL_MAILER=log nobody would ever receive the link).
    'verify_email' => (bool) env('TASKFLOW_VERIFY_EMAIL', ! in_array(env('MAIL_MAILER', 'log'), ['log', 'array'], true)),

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),
];
