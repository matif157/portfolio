<?php
/**
 * =====================================================
 * FILE: app.php
 * PURPOSE: Main SPS CRM application configuration
 *
 * WORKFLOW:
 * 1. Environment variables se values read hoti hain.
 * 2. Application name set hota hai.
 * 3. Environment set hota hai.
 * 4. Debug setting determine hoti hai.
 * 5. URL/timezone settings centralize hoti hain.
 * =====================================================
 */

declare(strict_types=1);


/*
 * Application configuration array return kar rahe hain.
 *
 * require/include karne wali file ke through
 * yeh array directly application ko mil jayega.
 */
return [

    /*
     * Application name.
     *
     * env() custom helper hai jo .env se value read karta hai.
     */
    'name' => env(
        'APP_NAME',
        'Syed Professional Services CRM'
    ),


    /*
     * Current environment.
     *
     * local:
     * XAMPP development
     *
     * production:
     * Live server
     */
    'env' => env(
        'APP_ENV',
        'local'
    ),


    /*
     * Debug mode.
     *
     * .env mein:
     *
     * APP_DEBUG=true
     *
     * hone par true.
     */
    'debug' => filter_var(
        env(
            'APP_DEBUG',
            false
        ),
        FILTER_VALIDATE_BOOLEAN
    ),


    /*
     * Application timezone.
     *
     * Pakistan-based development ke liye abhi Asia/Karachi.
     *
     * Future deployment mein company requirement ke according
     * timezone architecture expand ki ja sakti hai.
     */
    'timezone' => 'Asia/Karachi',
];