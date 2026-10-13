<?php

return [
    // Maximum size of a single uploaded attachment, in kilobytes.
    'max_upload_kb' => (int) env('TASKFLOW_MAX_UPLOAD_KB', 20480),

    // Load the demo workspace on first deploy (only when the users table is empty).
    // Shown in front of currency custom fields.
    'currency_symbol' => env('TASKFLOW_CURRENCY_SYMBOL', '₱'),

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', false),
];
