<?php
/**
 * =====================================================
 * FILE: app.php
 * PURPOSE: CRM ki general application configuration
 * WORKFLOW:
 * 1. Environment values read hoti hain.
 * 2. Application name, URL aur debug mode prepare hota hai.
 * 3. Baqi application isi central configuration ko use karti hai.
 * =====================================================
 */

declare(strict_types=1);

/*
 * Application configuration array return kar rahe hain.
 *
 * Is file ko require karne par PHP ko yahi array milega.
 */
return [

    /*
     * CRM ka display name.
     */
    'name' => Env::get(
        'APP_NAME',
        'Syed Professional Services CRM'
    ),

    /*
     * Current environment.
     *
     * local:
     * Development/testing
     *
     * production:
     * Live company system
     */
    'environment' => Env::get(
        'APP_ENV',
        'local'
    ),

    /*
     * Debug mode.
     *
     * Local development mein true useful hai.
     * Production mein false hona chahiye.
     */
    'debug' => Env::bool(
        'APP_DEBUG',
        false
    ),

    /*
     * Application ka base URL.
     *
     * Redirects aur generated links mein later use hoga.
     */
    'url' => rtrim(
        (string) Env::get(
            'APP_URL',
            'http://localhost/syed-professional-crm/public'
        ),
        '/'
    ),

    /*
     * Default timezone.
     *
     * SPS future mein:
     *
     * invoices
     * deadlines
     * reminders
     * workflow timestamps
     * audit logs
     *
     * sab mein timezone consistency require karega.
     *
     * America/New_York ko company-side default ke taur par
     * architecture mein rakh rahe hain.
     *
     * Client/user specific timezone later separately support hogi.
     */
    'timezone' => 'America/New_York',
];