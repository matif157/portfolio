<?php
/**
 * 
 * =====================================================
 * FILE: 500.php
 * PURPOSE: Internal server error ke liye safe user-facing page
 * WORKFLOW:
 * 1. Application mein unexpected serious error hota hai.
 * 2. ErrorHandler detailed information log karta hai.
 * 3. User ko raw PHP error nahi dikhaya jata.
 * 4. Yeh friendly error page show hota hai.
 * =====================================================
 */
/*
 * Logger class load kar rahe hain.
 */
require_once $projectRoot . '/app/Core/Logger.php';

/*
 * ErrorHandler class load kar rahe hain.
 */
require_once $projectRoot . '/app/Core/ErrorHandler.php';
?>
/*
 * Logger create kar rahe hain.
 *
 * storage/logs folder mein application logs save honge.
 */
$logger = new Logger(
    $projectRoot . '/storage/logs'
);

/*
 * Logger ko container mein register kar rahe hain.
 */
$container->set(
    'logger',
    $logger
);

/*
 * ErrorHandler create kar rahe hain.
 *
 * Debug setting config se aa rahi hai.
 */
$errorHandler = new ErrorHandler(
    $logger,
    $appConfig['debug'],
    $projectRoot
);

/*
 * ErrorHandler ko application-wide register kar rahe hain.
 *
 * Is point ke baad PHP errors/exceptions central handler tak
 * pohanchengi.
 */
$errorHandler->register();

/*
 * ErrorHandler ko container mein bhi save kar rahe hain.
 */
$container->set(
    'error_handler',
    $errorHandler
);
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>System Error | SPS CRM</title>

    <style>
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

        /*
         * Debug information sirf development mein visible hogi.
         *
         * Production mein ErrorHandler debug false rakhega,
         * isliye sensitive technical details user ko nahi milengi.
         */
        .debug-box {
            margin-top: 25px;
            padding: 15px;
            text-align: left;
            background: #f1f1f1;
            border-radius: 8px;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <main class="error-box">

        <div class="error-code">
            500
        </div>

        <h1 class="error-title">
            Something Went Wrong
        </h1>

        <p class="error-text">
            System mein unexpected problem aa gayi hai.
            Aapka data safely handle kiya ja raha hai.
            Please thori dair baad dobara try karein.
        </p>

        <?php if ($debug): ?>

            <!--
                Debug mode sirf local development ke liye hai.
                Production mein APP_DEBUG=false hoga.
            -->
            <div class="debug-box">
                <strong>Development Mode</strong>

                <p>
                    Detailed error information server log mein save ho chuki hai.
                </p>
            </div>

        <?php endif; ?>

        <a
            class="home-link"
            href="/syed-professional-crm/public/"
        >
            Back to CRM
        </a>

    </main>

</body>

</html>