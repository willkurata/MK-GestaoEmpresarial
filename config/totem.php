<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Totem de Impressão Autônoma (WIL-46)
    |--------------------------------------------------------------------------
    |
    | Settings for the self-service print kiosk backend. Customer files are
    | stored on a private disk and deleted after printing or, at the latest,
    | after "file_retention_hours" (LGPD).
    |
    */

    'disk' => env('TOTEM_DISK', 'local'),

    'customer_url' => env('TOTEM_CUSTOMER_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/totem'),

    'accepted_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    'accepted_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],

    'max_file_mb' => (int) env('TOTEM_MAX_FILE_MB', 50),

    'max_pages' => (int) env('TOTEM_MAX_PAGES', 300),

    'qr_window_seconds' => 60,

    'pairing_code_ttl_minutes' => 30,

    'draft_ttl_minutes' => (int) env('TOTEM_DRAFT_TTL_MINUTES', 30),

    'payment_ttl_minutes' => (int) env('TOTEM_PAYMENT_TTL_MINUTES', 15),

    'lease_seconds' => (int) env('TOTEM_LEASE_SECONDS', 300),

    'max_print_attempts' => (int) env('TOTEM_MAX_PRINT_ATTEMPTS', 3),

    'file_retention_hours' => (int) env('TOTEM_FILE_RETENTION_HOURS', 24),

    'offline_after_seconds' => (int) env('TOTEM_OFFLINE_AFTER_SECONDS', 120),

    'ready_visible_minutes' => 15,

    'pix' => [
        'driver' => env('TOTEM_PIX_DRIVER', 'sandbox'),
        'webhook_secret' => env('TOTEM_PIX_WEBHOOK_SECRET'),
    ],

];
