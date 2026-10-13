<?php
return [
    'ai' => [
        'enabled' => (bool)getenv('AI_ENABLED'),
        'endpoint' => getenv('AI_ENDPOINT') ?: '',
        'api_key' => getenv('AI_API_KEY') ?: '',
        'model' => getenv('AI_MODEL') ?: '',
    ],
    'voice' => [
        'enabled' => (bool)getenv('VOICE_ENABLED'),
        'account_sid' => getenv('TWILIO_ACCOUNT_SID') ?: '',
        'auth_token' => getenv('TWILIO_AUTH_TOKEN') ?: '',
        'from_number' => getenv('TWILIO_FROM_NUMBER') ?: '',
    ],
];
