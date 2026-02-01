<?php

return [
    /**
     * Enable/disable ERP integration
     * false = development mode (manual users only)
     * true = production mode (sync dari ERP)
     */
    'enabled' => env('ERP_ENABLED', false),

    /**
     * ERP API Configuration
     */
    'api_url' => env('ERP_API_URL', 'https://erp.plnip.co.id/api/employees'),
    'api_key' => env('ERP_API_KEY', ''),

    /**
     * Sync configuration
     */
    'timeout' => env('ERP_SYNC_TIMEOUT', 30),
    'schedule' => env('ERP_SYNC_SCHEDULE', '02:00'), // Format: HH:MM (24-hour)

    /**
     * Retry configuration
     */
    'max_retries' => env('ERP_MAX_RETRIES', 3),
    'retry_delay' => env('ERP_RETRY_DELAY', 60), // seconds

    /**
     * SSL configuration
     */
    'verify_ssl' => env('ERP_VERIFY_SSL', true),

    /**
     * JIT (Just-In-Time) validation pada login
     * Cek status user di ERP ketika login
     */
    'jit_validation' => env('ERP_JIT_VALIDATION', false),

    /**
     * Webhook configuration (future)
     * Jika ERP mendukung webhook untuk push updates
     */
    'webhook_enabled' => env('ERP_WEBHOOK_ENABLED', false),
    'webhook_token' => env('ERP_WEBHOOK_TOKEN', ''),
];
