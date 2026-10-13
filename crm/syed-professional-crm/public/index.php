<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

\App\Core\Url::setBasePath('');

require_once __DIR__ . '/../resources/views/dashboard/index.php';
