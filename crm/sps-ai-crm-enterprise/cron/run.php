<?php
require __DIR__.'/../bootstrap/env.php';
spl_autoload_register(function($c){if(str_starts_with($c,'App\\')){$f=__DIR__.'/../app/'.str_replace('\\','/',substr($c,4)).'.php';if(is_file($f))require $f;}});
echo "Recurring: ".(new App\Jobs\RecurringProjectJob)->handle().PHP_EOL;
echo "Overdue invoices: ".(new App\Jobs\OverdueInvoiceJob)->handle().PHP_EOL;
echo "Upcoming reminders: ".(new App\Jobs\ReminderJob)->handle().PHP_EOL;
