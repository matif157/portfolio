<?php
return [
    'name' => getenv('APP_NAME') ?: 'Syed Professional Services CRM',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim(getenv('APP_URL') ?: '', '/'),
    'key' => getenv('APP_KEY') ?: 'CHANGE_ME',
    'mail_from' => getenv('MAIL_FROM') ?: 'invoice@syedpro.com',
];
