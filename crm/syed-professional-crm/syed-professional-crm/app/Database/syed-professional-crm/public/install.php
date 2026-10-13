<?php
/**
 * =====================================================
 * FILE: install.php
 * PURPOSE: Local XAMPP development mein migrations aur
 *          seeders ko manually execute karna
 *
 * WORKFLOW:
 * 1. Application bootstrap load hota hai.
 * 2. Database connection obtain hoti hai.
 * 3. MigrationRunner pending migrations run karta hai.
 * 4. SeederRunner pending seeders run karta hai.
 * 5. Browser mein clear installation result show hota hai.
 *
 * SECURITY NOTE:
 * Yeh development installer hai.
 * Production deployment se pehle isay remove/disable karna hoga.
 * =====================================================
 */

declare(strict_types=1);


/*
 * Project root calculate kar rahe hain.
 *
 * __DIR__ = PHP magic constant.
 *
 * Yeh current file yani public/ ka absolute directory deta hai.
 *
 * dirname() = PHP built-in function.
 *
 * public se ek level upar project root mil jata hai.
 */
$projectRoot = dirname(__DIR__);


/*
 * Application bootstrap load kar rahe hain.
 *
 * require_once = PHP built-in statement.
 *
 * Is se configuration, database aur required core classes
 * application environment mein available honge.
 */
require_once $projectRoot . '/bootstrap/app.php';


/*
 * MigrationRunner class load kar rahe hain.
 */
require_once $projectRoot . '/app/Database/MigrationRunner.php';


/*
 * SeederRunner class load kar rahe hain.
 */
require_once $projectRoot . '/app/Database/SeederRunner.php';


/*
 * Bootstrap/container se database connection obtain karna.
 *
 * IMPORTANT:
 * Yeh line assume karti hai ke hamare Container mein
 * PDO connection "db" key ke under registered hai.
 */
$pdo = $container->get('db');


/*
 * Result arrays prepare kar rahe hain.
 */
$migrations = [];
$seeders = [];
$error = null;


/*
 * Installation action sirf jab browser se
 * install request submit ho.
 *
 * $_SERVER = PHP predefined superglobal array.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    try {

        /*
         * Migration runner create kar rahe hain.
         */
        $migrationRunner = new MigrationRunner(
            $pdo,
            $projectRoot . '/database/migrations'
        );

        /*
         * Pending migrations execute.
         */
        $migrations = $migrationRunner->run();


        /*
         * Seeder runner create kar rahe hain.
         */
        $seederRunner = new SeederRunner(
            $pdo,
            $projectRoot . '/database/seeds'
        );

        /*
         * Pending seeders execute.
         */
        $seeders = $seederRunner->run();

    } catch (Throwable $exception) {

        /*
         * Detailed technical error user ko show nahi karenge.
         *
         * Real application mein ErrorHandler isay log karega.
         *
         * Abhi local installer mein simple message rakh rahe hain.
         */
        $error =
            'Installation failed. '
            . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SPS CRM Installation</title>

    <style>

        body {
            margin: 0;
            padding: 40px;
            background: #f5f7fa;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;
            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
        }

        .success {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #eaf7ee;
        }

        .error {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fdecec;
        }

        .section {
            margin-top: 30px;
        }

        li {
            margin-bottom: 8px;
        }

        button {
            border: 0;
            padding: 12px 20px;
            border-radius: 7px;
            cursor: pointer;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>
        SPS CRM Installation
    </h1>

    <p>
        Bhai, yahan se database migrations aur seeders
        safely run kiye ja sakte hain.
    </p>


    <?php if ($error !== null): ?>

        <div class="error">

            <strong>
                Installation Error
            </strong>

            <p>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        </div>

    <?php endif; ?>


    <?php if ($migrations !== [] || $seeders !== []): ?>

        <div class="success">

            <strong>
                Installation completed successfully.
            </strong>

        </div>

    <?php endif; ?>


    <div class="section">

        <h2>
            Migrations
        </h2>

        <?php if ($migrations === []): ?>

            <p>
                No new migrations were pending.
            </p>

        <?php else: ?>

            <ul>

                <?php foreach ($migrations as $migration): ?>

                    <li>
                        <?= htmlspecialchars(
                            $migration,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="section">

        <h2>
            Seeders
        </h2>

        <?php if ($seeders === []): ?>

            <p>
                No new seeders were pending.
            </p>

        <?php else: ?>

            <ul>

                <?php foreach ($seeders as $seeder): ?>

                    <li>
                        <?= htmlspecialchars(
                            $seeder,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="section">

        <form method="POST">

            <button type="submit">
                Run Installation
            </button>

        </form>

    </div>

</div>

</body>

</html>