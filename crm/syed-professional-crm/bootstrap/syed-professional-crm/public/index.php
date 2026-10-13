<?php
/**
 * =====================================================
 * FILE: public/index.php
 * PURPOSE: CRM ka main frontend entry point
 *
 * WORKFLOW:
 *
 * Browser
 *   ↓
 * public/index.php
 *   ↓
 * Dashboard view
 *
 * IMPORTANT:
 * Abhi hum frontend build kar rahe hain.
 * Backend authentication/database ko intentionally
 * is stage par connect nahi kar rahe.
 *
 * Baad mein:
 *
 * public/index.php
 *   ↓
 * bootstrap/app.php
 *   ↓
 * Router
 *   ↓
 * Controller
 *   ↓
 * View
 *
 * =====================================================
 */


/*
|--------------------------------------------------------------------------
| DEVELOPMENT FRONTEND MODE
|--------------------------------------------------------------------------
|
| Bhai abhi bootstrap/app.php mein syntax error hai aur
| hum frontend ko pehle visually build kar rahe hain.
|
| Isliye is temporary frontend stage mein bootstrap ko
| load nahi kar rahe.
|
| Backend integration phase mein ye entry point proper
| application bootstrap ke saath connect hoga.
|
*/


/*
|--------------------------------------------------------------------------
| DASHBOARD VIEW LOAD
|--------------------------------------------------------------------------
|
| require = PHP built-in language construct.
|
| Iska purpose dashboard ka PHP/HTML UI load karna hai.
|
| __DIR__ = PHP magic constant.
|
| Ye current public folder ka absolute filesystem path
| provide karta hai.
|
| ../resources/views/dashboard.php
| public folder se bahar ja kar resources/views ke
| dashboard page ko load karta hai.
|
*/

require_once __DIR__ . '/../resources/views/dashboard.php';