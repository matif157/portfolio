<?php
require __DIR__.'/../bootstrap.php';
echo 'Recurring: '.(new App\Jobs\RecurringProjectsJob)->run().PHP_EOL;
echo 'Deadlines: '.(new App\Jobs\DeadlineJob)->run().PHP_EOL;
echo 'Events: '.(new App\Jobs\AutomationJob)->run().PHP_EOL;
echo 'Missing requirements: '.(new App\Jobs\MissingRequirementJob)->handle().PHP_EOL;
