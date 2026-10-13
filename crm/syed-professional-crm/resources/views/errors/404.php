<?php
/**
 * =====================================================
 * FILE: 404.php
 * PURPOSE: User-friendly "Page Not Found" screen
 * WORKFLOW:
 * 1. Router ko requested page nahi milti.
 * 2. ErrorHandler 404 response prepare karta hai.
 * 3. Yeh view user ko simple explanation deti hai.
 * =====================================================
 */

/*
 * htmlspecialchars() = PHP built-in security function.
 *
 * Is page mein future dynamic values print karte waqt
 * XSS se protection ke liye use ki jayegi.
 */
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Page Not Found | SPS CRM</title>

    <style>
        /*
         * Basic emergency styling.
         *
         * Bhai final professional design hum later dedicated
         * CSS system mein banayenge.
         */
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
        }

        .error-box {
            max-width: 560px;
            padding: 40px;
            text-align: center;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
        }

        .error-code {
            font-size: 64px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 26px;
            margin-bottom: 12px;
        }

        .error-text {
            color: #666;
            line-height: 1.6;
        }

        .home-link {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 18px;
            text-decoration: none;
            border-radius: 7px;
            background: #222;
            color: #fff;
        }
    </style>
</head>

<body>

    <main class="error-box">

        <div class="error-code">
            404
        </div>

        <h1 class="error-title">
            Page Not Found
        </h1>

        <p class="error-text">
            Bhai, yeh page available nahi hai ya URL change ho chuka hai.
            Please wapas CRM ke main page par chalein.
        </p>

        <a
            class="home-link"
            href="/syed-professional-crm/public/"
        >
            Back to CRM
        </a>

    </main>

</body>

</html>