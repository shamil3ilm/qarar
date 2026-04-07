<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('ERP_DEFAULT_CURRENCY', 'SAR'),
        'timezone' => env('ERP_DEFAULT_TIMEZONE', 'Asia/Riyadh'),
        'language' => env('ERP_DEFAULT_LANGUAGE', 'en'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Approval Threshold
    |--------------------------------------------------------------------------
    | POs whose total exceeds this amount are automatically routed for approval.
    | Set to 0 to require approval for all POs.
    */
    'po_approval_threshold' => env('ERP_PO_APPROVAL_THRESHOLD', 10000),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'login' => [
            'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
            'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
        ],
        'password' => [
            'min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Countries
    |--------------------------------------------------------------------------
    */
    'countries' => [
        'SA' => [
            'name' => 'Saudi Arabia',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'tax_scheme' => 'VAT',
            'vat_rate' => 15,
        ],
        'AE' => [
            'name' => 'United Arab Emirates',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'tax_scheme' => 'VAT',
            'vat_rate' => 5,
        ],
        'QA' => [
            'name' => 'Qatar',
            'currency' => 'QAR',
            'timezone' => 'Asia/Riyadh',
            'tax_scheme' => 'VAT',
            'vat_rate' => 0, // No VAT yet
        ],
        'OM' => [
            'name' => 'Oman',
            'currency' => 'OMR',
            'timezone' => 'Asia/Dubai',
            'tax_scheme' => 'VAT',
            'vat_rate' => 5,
        ],
        'BH' => [
            'name' => 'Bahrain',
            'currency' => 'BHD',
            'timezone' => 'Asia/Riyadh',
            'tax_scheme' => 'VAT',
            'vat_rate' => 10,
        ],
        'KW' => [
            'name' => 'Kuwait',
            'currency' => 'KWD',
            'timezone' => 'Asia/Riyadh',
            'tax_scheme' => 'VAT',
            'vat_rate' => 0, // No VAT yet
        ],
        'IN' => [
            'name' => 'India',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'tax_scheme' => 'GST',
            'gst_rates' => [0, 5, 12, 18, 28],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SR', 'decimals' => 2],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'decimals' => 2],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QR', 'decimals' => 2],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => 'OMR', 'decimals' => 3],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => 'BD', 'decimals' => 3],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KD', 'decimals' => 3],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency (top-level alias for CurrencyService)
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('ERP_DEFAULT_CURRENCY', 'SAR'),

    /*
    |--------------------------------------------------------------------------
    | Number Format
    |--------------------------------------------------------------------------
    | Pattern used by NumberGeneratorService.
    | Available placeholders: {prefix}, {year}, {month}, {number}
    */
    'number_format' => env('ERP_NUMBER_FORMAT', '{prefix}-{year}-{number}'),

    /*
    |--------------------------------------------------------------------------
    | TDS Education / Health Cess Rate
    |--------------------------------------------------------------------------
    | The health & education cess applied on top of TDS (as a decimal fraction).
    | Current statutory rate is 4% (0.04). Override via TDS_CESS_RATE in .env.
    */
    'tds_cess_rate' => env('TDS_CESS_RATE', '0.04'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Back-date Days
    |--------------------------------------------------------------------------
    | How many days into the past a user may back-date a transaction.
    */
    'max_backdate_days' => (int) env('ERP_MAX_BACKDATE_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Default Lead Time Days (MRP Planning)
    |--------------------------------------------------------------------------
    | Fallback lead time (in days) used by MRP when a product does not define
    | its own lead_time_days or default_supplier_lead_days.
    */
    'default_lead_time_days' => (int) env('ERP_DEFAULT_LEAD_TIME_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Default Chart-of-Account IDs
    |--------------------------------------------------------------------------
    | These are fall-back account IDs used when a contact or product does not
    | specify its own account.  Set them per-environment or override in the
    | organization settings at runtime.
    |
    | Values should be integer IDs that match rows in the `accounts` table.
    | A null value means the feature is not configured and will cause a
    | validation error at journal-posting time until properly set.
    */
    'default_accounts' => [
        'receivable'     => env('ERP_ACCOUNT_RECEIVABLE', null),
        'payable'        => env('ERP_ACCOUNT_PAYABLE', null),
        'sales'          => env('ERP_ACCOUNT_SALES', null),
        'expense'        => env('ERP_ACCOUNT_EXPENSE', null),
        'cash'           => env('ERP_ACCOUNT_CASH', null),
        'tax_payable'    => env('ERP_ACCOUNT_TAX_PAYABLE', null),
        'tax_receivable' => env('ERP_ACCOUNT_TAX_RECEIVABLE', null),
        'salary_expense' => env('ERP_ACCOUNT_SALARY_EXPENSE', null),
        'salary_payable' => env('ERP_ACCOUNT_SALARY_PAYABLE', null),
        'wip_inventory'  => env('ERP_ACCOUNT_WIP_INVENTORY', null),
        'fg_inventory'   => env('ERP_ACCOUNT_FG_INVENTORY', null),
        'rm_inventory'   => env('ERP_ACCOUNT_RM_INVENTORY', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Document Generation
    |--------------------------------------------------------------------------
    | Controls async PDF generation and email dispatch that runs via the
    | 'documents' queue worker after an invoice transitions to SENT status.
    |
    | auto_generate_pdf      — set false to disable PDF generation entirely.
    | send_email_on_dispatch — set true to email the customer when the invoice
    |                          is sent (requires 'invoice_sent' email template).
    */
    'invoice' => [
        'auto_generate_pdf'      => env('ERP_INVOICE_AUTO_PDF', true),
        'send_email_on_dispatch' => env('ERP_INVOICE_SEND_EMAIL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statutory / Deduction Accounts
    |--------------------------------------------------------------------------
    | Maps salary-component codes (e.g. GOSI, PF, ESI) to their liability
    | account IDs for journal entries created during payroll processing.
    */
    'statutory_accounts' => [
        'gosi_employee'  => env('ERP_STATUTORY_GOSI_EE', null),
        'gosi_employer'  => env('ERP_STATUTORY_GOSI_ER', null),
        'pf_employee'    => env('ERP_STATUTORY_PF_EE', null),
        'pf_employer'    => env('ERP_STATUTORY_PF_ER', null),
        'esi_employee'   => env('ERP_STATUTORY_ESI_EE', null),
        'esi_employer'   => env('ERP_STATUTORY_ESI_ER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    | slow_query_threshold_ms — queries at or above this duration (in ms) are
    |                           written to the 'slow_queries' log channel.
    |                           Set to 0 to disable slow query logging entirely.
    */
    'performance' => [
        'slow_query_threshold_ms' => env('SLOW_QUERY_THRESHOLD_MS', 500),
        // Set to 0 to disable slow query logging
        'response_budget_ms' => env('RESPONSE_BUDGET_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    | Controls the Redis-backed circuit breaker used to protect calls to
    | external APIs (e.g. ZATCA / CompliPay).
    |
    | threshold   — consecutive failures before the circuit opens
    | open_ttl    — seconds to keep the circuit open (fast-fail window)
    | counter_ttl — TTL for the failure counter key
    */
    'circuit_breaker' => [
        'threshold'   => env('CIRCUIT_BREAKER_THRESHOLD', 5),
        'open_ttl'    => env('CIRCUIT_BREAKER_OPEN_TTL', 60),
        'counter_ttl' => env('CIRCUIT_BREAKER_COUNTER_TTL', 120),
    ],
];
