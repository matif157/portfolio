<?php
return [
 'name'=>getenv('APP_NAME') ?: 'SPS CRM',
 'env'=>getenv('APP_ENV') ?: 'local',
 'debug'=>filter_var(getenv('APP_DEBUG') ?: true,FILTER_VALIDATE_BOOLEAN),
 'url'=>rtrim(getenv('APP_URL') ?: 'http://localhost','/'),
 'session'=>getenv('SESSION_NAME') ?: 'spscrm',
 'mail_from'=>getenv('MAIL_FROM') ?: 'no-reply@example.com'
];
