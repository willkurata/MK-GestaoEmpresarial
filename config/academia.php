<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Academia
    |--------------------------------------------------------------------------
    |
    | Settings for the gym management API (/api/academia/v1). It is isolated
    | from MK Gestão: its own tables (gym_*), guard and token format.
    |
    */

    'token_ttl_days' => (int) env('ACADEMIA_TOKEN_TTL_DAYS', 30),

    /*
     * Days before the due date on which a recurring invoice is generated.
     */
    'invoice_lead_days' => (int) env('ACADEMIA_INVOICE_LEAD_DAYS', 5),

    /*
     * Version of the biometric consent terms currently presented to students.
     */
    'biometric_terms_version' => env('ACADEMIA_BIOMETRIC_TERMS_VERSION', '2026-11'),

    'payments' => [
        /*
         * Only the sandbox gateway exists until the commercial gateway (Asaas, Efí...) is chosen.
         */
        'gateway' => env('ACADEMIA_PAYMENT_GATEWAY', 'sandbox'),
        'webhook_secret' => env('ACADEMIA_PAYMENT_WEBHOOK_SECRET'),
    ],

];
