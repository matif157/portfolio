<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors for production

// Your Twilio Credentials
define('TWILIO_ACCOUNT_SID', 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
define('TWILIO_AUTH_TOKEN', 'XXXXXXXXXX_AUTH_TOKEN_XXXXXXXXXX');
define('TWILIO_PHONE_NUMBER', '+18669635928');

// Simple authentication - ensure all session variables are set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid('user_');
    $_SESSION['username'] = 'User_' . rand(1000, 9999);
    $_SESSION['user_phone'] = '+1' . rand(200, 999) . rand(100, 999) . rand(1000, 9999);
}

// Safely get session variables with defaults
 $user_id = $_SESSION['user_id'] ?? '';
 $username = $_SESSION['username'] ?? 'Unknown User';
 $user_phone = $_SESSION['user_phone'] ?? '+1234567890';

// Handle POST requests for generating tokens
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set headers before any output
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'generate_token':
                    echo generateToken();
                    exit;
                    
                case 'make_call':
                    if (!isset($_POST['to']) || empty($_POST['to'])) {
                        throw new Exception('Phone number is required');
                    }
                    echo makeCall($_POST['to']);
                    exit;
                    
                case 'send_sms':
                    if (!isset($_POST['to']) || !isset($_POST['message'])) {
                        throw new Exception('Phone number and message are required');
                    }
                    echo sendSMS($_POST['to'], $_POST['message']);
                    exit;
                    
                case 'save_contact':
                    if (!isset($_POST['name']) || !isset($_POST['phone'])) {
                        throw new Exception('Name and phone are required');
                    }
                    echo saveContact($_POST);
                    exit;
                    
                case 'delete_contact':
                    if (!isset($_POST['id'])) {
                        throw new Exception('Contact ID is required');
                    }
                    echo deleteContact($_POST['id']);
                    exit;
                    
                default:
                    throw new Exception('Invalid action');
            }
        } else {
            throw new Exception('No action specified');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Generate Twilio Access Token
function generateToken() {
    // Check if Twilio library exists
    if (!file_exists(__DIR__ . '/Twilio/autoload.php')) {
        throw new Exception('Twilio library not found. Please install Twilio SDK.');
    }
    
    require_once __DIR__ . '/Twilio/autoload.php';
    
    // Generate unique client name
    $clientName = 'client_' . ($_SESSION['user_id'] ?? 'anonymous') . '_' . time();
    
    // You need to create API Key in Twilio Console
    $apiKeySid = 'SKXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
    $apiKeySecret = 'your_api_secret_here';
    
    if ($apiKeySid === 'SKXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' || $apiKeySecret === 'your_api_secret_here') {
        throw new Exception('Please configure your Twilio API Key and Secret in the code');
    }
    
    // Create access token
    $token = new \Twilio\Jwt\AccessToken(
        TWILIO_ACCOUNT_SID,
        $apiKeySid,
        $apiKeySecret,
        3600,
        $clientName
    );
    
    // Create Voice grant
    $voiceGrant = new \Twilio\Jwt\Grants\VoiceGrant();
    $voiceGrant->setOutgoingApplicationSid('APXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); // Your TwiML App SID
    
    // Allow incoming calls
    $voiceGrant->setIncomingAllow(true);
    
    $token->addGrant($voiceGrant);
    
    return json_encode([
        'success' => true,
        'token' => $token->toJWT(),
        'client_name' => $clientName
    ]);
}

// Make outbound call
function makeCall($to) {
    if (!file_exists(__DIR__ . '/Twilio/autoload.php')) {
        throw new Exception('Twilio library not found');
    }
    
    require_once __DIR__ . '/Twilio/autoload.php';
    
    try {
        $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        $call = $client->calls->create(
            $to,
            TWILIO_PHONE_NUMBER,
            [
                'url' => 'https://handler.twilio.com/twiml/EH3cc....', // Your TwiML URL
                'record' => true
            ]
        );
        
        return json_encode([
            'success' => true,
            'call_sid' => $call->sid,
            'message' => 'Call initiated successfully'
        ]);
    } catch (Exception $e) {
        throw new Exception('Call failed: ' . $e->getMessage());
    }
}

// Send SMS
function sendSMS($to, $message) {
    if (!file_exists(__DIR__ . '/Twilio/autoload.php')) {
        throw new Exception('Twilio library not found');
    }
    
    require_once __DIR__ . '/Twilio/autoload.php';
    
    try {
        $client = new \Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        $sms = $client->messages->create(
            $to,
            [
                'from' => TWILIO_PHONE_NUMBER,
                'body' => $message
            ]
        );
        
        return json_encode([
            'success' => true,
            'message_sid' => $sms->sid,
            'message' => 'SMS sent successfully'
        ]);
    } catch (Exception $e) {
        throw new Exception('SMS failed: ' . $e->getMessage());
    }
}

// Save contact
function saveContact($data) {
    $contacts = json_decode($_SESSION['contacts'] ?? '[]', true) ?: [];
    
    $contact = [
        'id' => uniqid('contact_'),
        'name' => $data['name'] ?? '',
        'phone' => $data['phone'] ?? '',
        'email' => $data['email'] ?? '',
        'avatar' => $data['avatar'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $contacts[] = $contact;
    $_SESSION['contacts'] = json_encode($contacts);
    
    return json_encode([
        'success' => true,
        'contact' => $contact
    ]);
}

// Delete contact
function deleteContact($id) {
    $contacts = json_decode($_SESSION['contacts'] ?? '[]', true) ?: [];
    
    $contacts = array_filter($contacts, function($contact) use ($id) {
        return $contact['id'] !== $id;
    });
    
    $_SESSION['contacts'] = json_encode(array_values($contacts));
    
    return json_encode([
        'success' => true
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Twilio Voice & SMS System</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Twilio Voice SDK -->
    <script src="https://sdk.twilio.com/js/voice/releases/2.2.0/twilio-voice.min.js"></script>
    
    <style>
        :root {
            --primary: #F22F46;
            --secondary: #0D122B;
            --accent: #7209b7;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --dark-glass-bg: rgba(33, 37, 41, 0.85);
        }
        
        /* Dark mode styles */
        [data-theme="dark"] {
            --glass-bg: rgba(33, 37, 41, 0.85);
            --dark-glass-bg: rgba(255, 255, 255, 0.85);
            --light: #212529;
            --dark: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background 0.5s ease;
        }
        
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0D122B 0%, #2D1B69 100%);
        }
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .glass-card:hover::before {
            opacity: 1;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .twilio-brand {
            color: var(--primary);
            font-weight: 700;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transform: rotate(45deg);
        }
        
        .call-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            font-size: 24px;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .call-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .call-btn:active::before {
            width: 100px;
            height: 100px;
        }
        
        .call-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .dial-pad {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] .dial-pad {
            background: var(--dark);
        }
        
        .dial-btn {
            width: 80px;
            height: 80px;
            margin: 10px;
            font-size: 28px;
            font-weight: bold;
            border-radius: 50%;
            border: 2px solid #dee2e6;
            background: white;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .dial-btn {
            background: var(--light);
            border-color: var(--secondary);
            color: var(--dark);
        }
        
        .dial-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(242, 47, 70, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }
        
        .dial-btn:active::before {
            width: 100px;
            height: 100px;
        }
        
        .dial-btn:active {
            transform: scale(0.95);
        }
        
        .call-timer {
            font-size: 48px;
            font-weight: 800;
            color: var(--primary);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            font-family: 'Courier New', monospace;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            position: relative;
        }
        
        .status-indicator::after {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            background: inherit;
            opacity: 0.3;
            animation: pulse-ring 2s infinite;
        }
        
        .status-connected { background-color: var(--success); }
        .status-disconnected { background-color: var(--danger); }
        .status-ringing { 
            background-color: var(--warning);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 0.5;
            }
            100% {
                transform: scale(1.4);
                opacity: 0;
            }
        }
        
        .contact-item {
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 10px;
            background: white;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .contact-item {
            background: var(--light);
            border-color: var(--secondary);
            color: var(--dark);
        }
        
        .contact-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(242, 47, 70, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .contact-item:hover::before {
            left: 100%;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
            border-color: var(--primary);
            transform: translateX(5px);
        }
        
        [data-theme="dark"] .contact-item:hover {
            background: var(--dark);
        }
        
        .call-log-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s;
            position: relative;
        }
        
        [data-theme="dark"] .call-log-item {
            border-color: var(--secondary);
        }
        
        .call-log-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: transparent;
            transition: background 0.3s;
        }
        
        .call-log-item:hover::before {
            background: var(--primary);
        }
        
        .call-log-item:hover {
            background: #f8f9fa;
        }
        
        [data-theme="dark"] .call-log-item:hover {
            background: var(--dark);
        }
        
        .sms-bubble {
            max-width: 70%;
            padding: 12px 15px;
            border-radius: 18px;
            margin: 5px 0;
            word-wrap: break-word;
            position: relative;
            animation: fadeInUp 0.3s;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .sms-sent {
            background: var(--primary);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        
        .sms-received {
            background: #e9ecef;
            color: var(--dark);
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        
        [data-theme="dark"] .sms-received {
            background: var(--secondary);
            color: var(--light);
        }
        
        .active-call-card {
            border: 3px solid var(--primary);
            animation: glow 2s infinite;
        }
        
        @keyframes glow {
            0%, 100% { border-color: var(--primary); box-shadow: 0 0 10px rgba(242, 47, 70, 0.5); }
            50% { border-color: #ff6b8b; box-shadow: 0 0 20px rgba(255, 107, 139, 0.8); }
        }
        
        .quality-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        [data-theme="dark"] .quality-bar {
            background: var(--secondary);
        }
        
        .quality-level {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s;
        }
        
        .quality-excellent { background: var(--success); }
        .quality-good { background: var(--warning); }
        .quality-poor { background: var(--danger); }
        
        /* Theme toggle button */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            max-width: 300px;
        }
        
        [data-theme="dark"] .notification {
            background: var(--dark);
            color: var(--light);
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            border-left: 4px solid var(--success);
        }
        
        .notification.error {
            border-left: 4px solid var(--danger);
        }
        
        .notification.info {
            border-left: 4px solid var(--primary);
        }
        
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            z-index: 999;
            transition: all 0.3s;
        }
        
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }
        
        /* Ripple effect */
        .ripple {
            position: relative;
            overflow: hidden;
        }
        
        .ripple::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .ripple:active::before {
            width: 300px;
            height: 300px;
        }
        
        /* Loading animation */
        .loader {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Tab navigation */
        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary);
            font-weight: 500;
            padding: 10px 15px;
            position: relative;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--primary);
            transition: width 0.3s;
        }
        
        .nav-tabs .nav-link.active::after {
            width: 100%;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
        }
        
        /* Search box */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }
        
        [data-theme="dark"] .search-box input {
            background: var(--dark);
            border-color: var(--secondary);
            color: var(--light);
        }
        
        .search-box input:focus {
            box-shadow: 0 0 0 3px rgba(242, 47, 70, 0.2);
            border-color: var(--primary);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        /* Sound toggle */
        .sound-toggle {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        [data-theme="dark"] .sound-toggle {
            background: rgba(33, 37, 41, 0.7);
        }
        
        .sound-toggle:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: scale(1.1);
        }
        
        [data-theme="dark"] .sound-toggle:hover {
            background: rgba(33, 37, 41, 0.9);
        }
        
        /* Stats card */
        .stats-card {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* Contact form */
        .contact-form {
            display: none;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        [data-theme="dark"] .contact-form {
            background: var(--dark);
            color: var(--light);
        }
        
        .contact-form.show {
            display: block;
            animation: fadeInDown 0.3s;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Keyboard shortcuts hint */
        .shortcuts-hint {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 12px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .shortcuts-hint.show {
            opacity: 1;
        }
        
        /* Voice visualization */
        .voice-visualizer {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
        }
        
        .voice-bar {
            width: 4px;
            height: 10px;
            background: var(--primary);
            margin: 0 2px;
            border-radius: 2px;
            animation: voice-wave 1s infinite ease-in-out;
        }
        
        .voice-bar:nth-child(2) { animation-delay: 0.1s; }
        .voice-bar:nth-child(3) { animation-delay: 0.2s; }
        .voice-bar:nth-child(4) { animation-delay: 0.3s; }
        .voice-bar:nth-child(5) { animation-delay: 0.4s; }
        
        @keyframes voice-wave {
            0%, 100% { height: 10px; }
            50% { height: 40px; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark navbar-expand-lg" style="background: var(--secondary);">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="https://www.twilio.com/marketing/bundles/company/img/logos/red/twilio-logo-red.png" 
                     height="30" class="me-2">
                <span class="twilio-brand">Voice & SMS System</span>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="sound-toggle" onclick="toggleSound()" title="Toggle Sound">
                    <i class="fas fa-volume-up" id="soundIcon"></i>
                </div>
                
                <div class="dropdown me-3">
                    <button class="btn btn-link text-white dropdown-toggle" type="button" id="settingsDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="showShortcuts()">
                            <i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>Export Data
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="clearData()">
                            <i class="fas fa-trash me-2"></i>Clear Data
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="showAbout()">
                            <i class="fas fa-info-circle me-2"></i>About
                        </a></li>
                    </ul>
                </div>
                
                <div class="user-avatar me-3">
                    <?php echo htmlspecialchars(substr($username ?? 'U', 0, 1)); ?>
                </div>
                <div class="text-white">
                    <div class="fw-bold"><?php echo htmlspecialchars($username ?? 'User'); ?></div>
                    <small><?php echo htmlspecialchars($user_phone ?? '+1234567890'); ?></small>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Left Column - Connection & Contacts -->
            <div class="col-lg-3 mb-4">
                <!-- Connection Status -->
                <div class="card glass-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-plug me-2"></i>Connection Status
                        </h5>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-indicator status-disconnected" id="statusIndicator"></span>
                                <span id="statusText">Disconnected</span>
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div id="qualityBar" class="quality-bar">
                                    <div id="qualityLevel" class="quality-level quality-poor" style="width: 0%"></div>
                                </div>
                            </div>
                            <small class="text-muted" id="qualityText">Connection quality: Unknown</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button id="connectBtn" class="btn btn-success ripple" onclick="connectTwilio()">
                                <i class="fas fa-play-circle me-2"></i>Connect
                            </button>
                            <button id="disconnectBtn" class="btn btn-outline-danger ripple" onclick="disconnectTwilio()" disabled>
                                <i class="fas fa-stop-circle me-2"></i>Disconnect
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-card">
                    <div class="stats-number" id="totalCalls">0</div>
                    <div class="stats-label">Total Calls</div>
                </div>
                
                <div class="stats-card" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <div class="stats-number" id="totalSMS">0</div>
                    <div class="stats-label">SMS Sent</div>
                </div>
                
                <!-- Quick Contacts -->
                <div class="card glass-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-address-book me-2"></i>Contacts
                            </h5>
                            <button class="btn btn-sm btn-primary" onclick="toggleContactForm()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <!-- Contact Form -->
                        <div class="contact-form" id="contactForm">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="contactName" placeholder="Name">
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" id="contactPhone" placeholder="Phone">
                            </div>
                            <div class="mb-3">
                                <input type="email" class="form-control" id="contactEmail" placeholder="Email (optional)">
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="saveContact()">
                                    <i class="fas fa-save me-2"></i>Save Contact
                                </button>
                            </div>
                        </div>
                        
                        <!-- Search Box -->
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="contactSearch" placeholder="Search contacts...">
                        </div>
                        
                        <div id="contactsList">
                            <div class="contact-item" onclick="dialContact('+1234567890', 'John Doe')">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3" style="width: 40px; height: 40px; background: #4361ee;">
                                        J
                                    </div>
                                    <div>
                                        <div class="fw-bold">John Doe</div>
                                        <small class="text-muted">+1234567890</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="contact-item" onclick="dialContact('+1234567891', 'Jane Smith')">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3" style="width: 40px; height: 40px; background: #3a0ca3;">
                                        J
                                    </div>
                                    <div>
                                        <div class="fw-bold">Jane Smith</div>
                                        <small class="text-muted">+1234567891</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="contact-item" onclick="dialContact('+18885551234', 'Support')">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3" style="width: 40px; height: 40px; background: #f72585;">
                                        S
                                    </div>
                                    <div>
                                        <div class="fw-bold">Support Team</div>
                                        <small class="text-muted">+18885551234</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Center Column - Dialer & Active Call -->
            <div class="col-lg-6 mb-4">
                <!-- Dialer -->
                <div class="card glass-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-phone-alt me-2"></i>Dialer
                        </h5>
                        
                        <div class="text-center mb-4">
                            <input type="text" id="dialNumber" class="form-control form-control-lg text-center mb-3" 
                                   placeholder="Enter phone number" 
                                   style="font-size: 24px; border-radius: 15px; letter-spacing: 2px;">
                            <div class="call-timer" id="callTimer">00:00</div>
                        </div>
                        
                        <div class="dial-pad mb-4">
                            <div class="row justify-content-center">
                                <?php 
                                $dialButtons = [
                                    ['1', ''],
                                    ['2', 'ABC'],
                                    ['3', 'DEF'],
                                    ['4', 'GHI'],
                                    ['5', 'JKL'],
                                    ['6', 'MNO'],
                                    ['7', 'PQRS'],
                                    ['8', 'TUV'],
                                    ['9', 'WXYZ'],
                                    ['*', ''],
                                    ['0', '+'],
                                    ['#', '']
                                ];
                                
                                foreach ($dialButtons as $button): ?>
                                <div class="col-4 text-center mb-3">
                                    <button class="btn dial-btn ripple" onclick="dialNumber('<?php echo $button[0]; ?>')">
                                        <?php echo $button[0]; ?>
                                        <?php if ($button[1]): ?>
                                        <br><small class="text-muted" style="font-size: 10px;"><?php echo $button[1]; ?></small>
                                        <?php endif; ?>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-6">
                                    <button class="btn btn-secondary w-100 py-2 ripple" onclick="clearDialNumber()">
                                        <i class="fas fa-eraser me-2"></i>Clear
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-warning w-100 py-2 ripple" onclick="backspaceDialNumber()">
                                        <i class="fas fa-backspace me-2"></i>Back
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button id="callButton" class="btn btn-success btn-lg py-3 ripple" onclick="makeCall()" disabled>
                                <i class="fas fa-phone me-2"></i>Make Call
                            </button>
                            <button id="endCallButton" class="btn btn-danger btn-lg py-3 ripple" onclick="endCall()" disabled>
                                <i class="fas fa-phone-slash me-2"></i>End Call
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Active Call -->
                <div class="card glass-card active-call-card" id="activeCallCard" style="display: none;">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-phone-volume me-2"></i>Active Call
                        </h5>
                        
                        <div class="text-center mb-4">
                            <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 32px;" id="callerAvatar">
                                ?
                            </div>
                            <h3 id="callerName">Active Call</h3>
                            <div id="callerNumber" class="text-muted mb-3">-</div>
                            <div id="callStatus" class="text-muted mb-4">
                                <i class="fas fa-circle me-2"></i>Connected
                            </div>
                            
                            <!-- Voice Visualization -->
                            <div class="voice-visualizer" id="voiceVisualizer">
                                <div class="voice-bar"></div>
                                <div class="voice-bar"></div>
                                <div class="voice-bar"></div>
                                <div class="voice-bar"></div>
                                <div class="voice-bar"></div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-4">
                                <button id="muteBtn" class="btn btn-outline-secondary w-100 py-3 ripple" onclick="toggleMute()">
                                    <i class="fas fa-microphone"></i>
                                </button>
                            </div>
                            <div class="col-4">
                                <button id="holdBtn" class="btn btn-outline-warning w-100 py-3 ripple" onclick="toggleHold()">
                                    <i class="fas fa-pause"></i>
                                </button>
                            </div>
                            <div class="col-4">
                                <button class="btn btn-outline-info w-100 py-3 ripple" onclick="openKeypad()">
                                    <i class="fas fa-th"></i>
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-dark w-100 py-3 ripple" onclick="sendDigit('*')">
                                    <i class="fas fa-asterisk"></i>
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-dark w-100 py-3 ripple" onclick="sendDigit('#')">
                                    <i class="fas fa-hashtag"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - SMS & Call Logs -->
            <div class="col-lg-3 mb-4">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-3" id="rightColumnTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms-pane" type="button" role="tab">
                            <i class="fas fa-sms me-2"></i>SMS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Logs
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="rightColumnTabContent">
                    <!-- SMS Messenger -->
                    <div class="tab-pane fade show active" id="sms-pane" role="tabpanel">
                        <div class="card glass-card">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Send to:</label>
                                    <input type="text" id="smsTo" class="form-control mb-2" placeholder="Phone number">
                                    <textarea id="smsMessage" class="form-control" rows="3" placeholder="Type your message..."></textarea>
                                </div>
                                
                                <button class="btn btn-primary w-100 mb-3 ripple" onclick="sendSMS()">
                                    <i class="fas fa-paper-plane me-2"></i>Send SMS
                                </button>
                                
                                <div id="smsConversation" style="max-height: 200px; overflow-y: auto;">
                                    <!-- SMS messages will appear here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Call Logs -->
                    <div class="tab-pane fade" id="logs-pane" role="tabpanel">
                        <div class="card glass-card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <i class="fas fa-history me-2"></i>Recent Calls
                                </h5>
                                
                                <div id="callLogs" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-phone-slash fa-2x mb-3"></i>
                                        <p>No call history</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Keypad Modal -->
    <div class="modal fade" id="keypadModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">DTMF Keypad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <?php for($i = 1; $i <= 9; $i++): ?>
                        <div class="col-4 mb-3">
                            <button class="btn btn-outline-primary w-100 py-3 ripple" onclick="sendDigit('<?php echo $i; ?>')">
                                <?php echo $i; ?>
                            </button>
                        </div>
                        <?php endfor; ?>
                        <div class="col-4 mb-3">
                            <button class="btn btn-outline-primary w-100 py-3 ripple" onclick="sendDigit('*')">*</button>
                        </div>
                        <div class="col-4 mb-3">
                            <button class="btn btn-outline-primary w-100 py-3 ripple" onclick="sendDigit('0')">0</button>
                        </div>
                        <div class="col-4 mb-3">
                            <button class="btn btn-outline-primary w-100 py-3 ripple" onclick="sendDigit('#')">#</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Incoming Call Modal -->
    <div class="modal fade" id="incomingCallModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-phone-volume me-2"></i>Incoming Call
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 32px;">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4 id="incomingCallerNumber">Unknown Number</h4>
                    <p class="text-muted">Incoming call...</p>
                    
                    <div class="mt-4">
                        <button class="btn btn-success btn-lg me-3 px-4 ripple" onclick="answerCall()">
                            <i class="fas fa-phone me-2"></i>Answer
                        </button>
                        <button class="btn btn-danger btn-lg px-4 ripple" data-bs-dismiss="modal" onclick="declineCall()">
                            <i class="fas fa-phone-slash me-2"></i>Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- About Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">About Twilio Voice & SMS System</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <img src="https://www.twilio.com/marketing/bundles/company/img/logos/red/twilio-logo-red.png" 
                             height="50" class="mb-3">
                        <h4>Twilio Voice & SMS System</h4>
                        <p class="text-muted">Version 2.0</p>
                    </div>
                    
                    <p>A modern communication platform built with Twilio APIs for voice calls and SMS messaging.</p>
                    
                    <div class="list-group">
                        <div class="list-group-item">
                            <i class="fas fa-code me-2"></i>
                            Built with PHP, Bootstrap, and Twilio SDK
                        </div>
                        <div class="list-group-item">
                            <i class="fas fa-paint-brush me-2"></i>
                            Modern UI with animations and transitions
                        </div>
                        <div class="list-group-item">
                            <i class="fas fa-mobile-alt me-2"></i>
                            Responsive design for all devices
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">© 2023 Twilio Voice & SMS System</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Theme Toggle Button -->
    <div class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </div>
    
    <!-- Floating Action Button -->
    <div class="fab" onclick="startConferenceCall()" title="Start Conference Call">
        <i class="fas fa-users"></i>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <!-- Keyboard Shortcuts Hint -->
    <div class="shortcuts-hint" id="shortcutsHint">
        <div class="mb-2"><strong>Keyboard Shortcuts:</strong></div>
        <div>C - Connect to Twilio</div>
        <div>D - Disconnect from Twilio</div>
        <div>M - Make Call</div>
        <div>E - End Call</div>
        <div>S - Send SMS</div>
        <div>K - Open Keypad</div>
        <div>T - Toggle Theme</div>
        <div>ESC - Close Modals</div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Twilio Variables
        let device = null;
        let activeCall = null;
        let callTimer = null;
        let callStartTime = null;
        let isMuted = false;
        let isOnHold = false;
        let connectionQuality = 0;
        let soundEnabled = true;
        let isDarkTheme = false;
        
        // Call Logs
        let callLogs = JSON.parse(localStorage.getItem('callLogs') || '[]');
        let smsHistory = JSON.parse(localStorage.getItem('smsHistory') || '[]');
        let contacts = JSON.parse(localStorage.getItem('contacts') || '[]');
        
        // Initialize UI
        function initUI() {
            updateCallLogs();
            updateSMSHistory();
            updateContacts();
            updateStats();
            
            // Check for saved theme preference
            if (localStorage.getItem('theme') === 'dark') {
                toggleTheme();
            }
            
            // Check for saved sound preference
            if (localStorage.getItem('soundEnabled') === 'false') {
                toggleSound();
            }
        }
        
        // Update call logs display
        function updateCallLogs() {
            const container = document.getElementById('callLogs');
            if (callLogs.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-phone-slash fa-2x mb-3"></i>
                        <p>No call history</p>
                    </div>`;
                return;
            }
            
            container.innerHTML = '';
            callLogs.slice(0, 10).forEach(log => {
                const item = document.createElement('div');
                item.className = 'call-log-item';
                
                let iconClass = 'fa-phone-alt';
                if (log.type === 'Incoming') iconClass = 'fa-phone-alt';
                else if (log.type === 'Outgoing') iconClass = 'fa-phone';
                else if (log.type === 'Missed') iconClass = 'fa-phone-slash';
                
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><i class="fas ${iconClass} me-2"></i>${log.number}</div>
                            <small class="text-muted">${log.type}</small>
                        </div>
                        <div class="text-end">
                            <div class="text-muted">${log.duration}</div>
                            <small class="text-muted">${log.time}</small>
                        </div>
                    </div>`;
                container.appendChild(item);
            });
        }
        
        // Update SMS history
        function updateSMSHistory() {
            const container = document.getElementById('smsConversation');
            container.innerHTML = '';
            
            smsHistory.slice(-5).forEach(sms => {
                const bubble = document.createElement('div');
                bubble.className = `sms-bubble ${sms.direction}`;
                bubble.innerHTML = `
                    <div class="small">${sms.message}</div>
                    <div class="text-end" style="font-size: 10px;">${sms.time}</div>`;
                container.appendChild(bubble);
            });
            
            container.scrollTop = container.scrollHeight;
        }
        
        // Update contacts
        function updateContacts() {
            const container = document.getElementById('contactsList');
            container.innerHTML = '';
            
            if (contacts.length === 0) {
                // Default contacts
                const defaultContacts = [
                    { name: 'John Doe', phone: '+1234567890', color: '#4361ee' },
                    { name: 'Jane Smith', phone: '+1234567891', color: '#3a0ca3' },
                    { name: 'Support Team', phone: '+18885551234', color: '#f72585' }
                ];
                
                defaultContacts.forEach(contact => {
                    addContactToList(contact);
                });
            } else {
                contacts.forEach(contact => {
                    addContactToList(contact);
                });
            }
        }
        
        // Add contact to list
        function addContactToList(contact) {
            const container = document.getElementById('contactsList');
            const item = document.createElement('div');
            item.className = 'contact-item';
            item.onclick = () => dialContact(contact.phone, contact.name);
            
            const color = contact.color || '#' + Math.floor(Math.random()*16777215).toString(16);
            const initial = contact.name.charAt(0).toUpperCase();
            
            item.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-3" style="width: 40px; height: 40px; background: ${color};">
                        ${initial}
                    </div>
                    <div>
                        <div class="fw-bold">${contact.name}</div>
                        <small class="text-muted">${contact.phone}</small>
                    </div>
                </div>`;
            container.appendChild(item);
        }
        
        // Update statistics
        function updateStats() {
            document.getElementById('totalCalls').textContent = callLogs.length;
            document.getElementById('totalSMS').textContent = smsHistory.length;
        }
        
        // Add call log
        function addCallLog(type, number, duration) {
            const log = {
                type: type,
                number: number,
                duration: duration,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                timestamp: Date.now()
            };
            
            callLogs.unshift(log);
            if (callLogs.length > 50) callLogs.pop();
            
            localStorage.setItem('callLogs', JSON.stringify(callLogs));
            updateCallLogs();
            updateStats();
            
            // Show notification for missed calls
            if (type === 'Missed') {
                showNotification(`Missed call from ${number}`, 'error');
            }
        }
        
        // Add SMS to history
        function addSMS(direction, number, message) {
            const sms = {
                direction: direction,
                number: number,
                message: message,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            };
            
            smsHistory.push(sms);
            if (smsHistory.length > 100) smsHistory.shift();
            
            localStorage.setItem('smsHistory', JSON.stringify(smsHistory));
            updateSMSHistory();
            updateStats();
            
            // Show notification
            if (direction === 'sms-sent') {
                showNotification('SMS sent successfully', 'success');
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            container.appendChild(notification);
            
            // Trigger animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    container.removeChild(notification);
                }, 300);
            }, 3000);
            
            // Play sound if enabled
            if (soundEnabled) {
                playNotificationSound(type);
            }
        }
        
        // Play notification sound
        function playNotificationSound(type) {
            const audio = new Audio();
            
            switch(type) {
                case 'success':
                    audio.src = 'https://assets.mixkit.co/sfx/preview/mixkit-achievement-bell-600.mp3';
                    break;
                case 'error':
                    audio.src = 'https://assets.mixkit.co/sfx/preview/mixkit-wrong-answer-fail-notification-946.mp3';
                    break;
                case 'incoming':
                    audio.src = 'https://assets.mixkit.co/sfx/preview/mixkit-phone-incoming-call-1243.mp3';
                    break;
                default:
                    audio.src = 'https://assets.mixkit.co/sfx/preview/mixkit-positive-notification-951.mp3';
            }
            
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
        
        // Toggle theme
        function toggleTheme() {
            isDarkTheme = !isDarkTheme;
            document.documentElement.setAttribute('data-theme', isDarkTheme ? 'dark' : 'light');
            
            const icon = document.getElementById('themeIcon');
            icon.className = isDarkTheme ? 'fas fa-sun' : 'fas fa-moon';
            
            localStorage.setItem('theme', isDarkTheme ? 'dark' : 'light');
        }
        
        // Toggle sound
        function toggleSound() {
            soundEnabled = !soundEnabled;
            
            const icon = document.getElementById('soundIcon');
            icon.className = soundEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
            
            localStorage.setItem('soundEnabled', soundEnabled);
            
            showNotification(`Sound ${soundEnabled ? 'enabled' : 'disabled'}`, 'info');
        }
        
        // Toggle contact form
        function toggleContactForm() {
            const form = document.getElementById('contactForm');
            form.classList.toggle('show');
            
            if (form.classList.contains('show')) {
                document.getElementById('contactName').focus();
            }
        }
        
        // Save contact
        async function saveContact() {
            const name = document.getElementById('contactName').value;
            const phone = document.getElementById('contactPhone').value;
            const email = document.getElementById('contactEmail').value;
            
            if (!name || !phone) {
                showNotification('Please enter name and phone number', 'error');
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_contact&name=${encodeURIComponent(name)}&phone=${encodeURIComponent(phone)}&email=${encodeURIComponent(email)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const contact = result.contact;
                    contacts.push(contact);
                    localStorage.setItem('contacts', JSON.stringify(contacts));
                    
                    addContactToList(contact);
                    toggleContactForm();
                    
                    // Clear form
                    document.getElementById('contactName').value = '';
                    document.getElementById('contactPhone').value = '';
                    document.getElementById('contactEmail').value = '';
                    
                    showNotification('Contact saved successfully', 'success');
                } else {
                    showNotification('Failed to save contact', 'error');
                }
            } catch (error) {
                console.error('Save contact error:', error);
                showNotification('Failed to save contact', 'error');
            }
        }
        
        // Delete contact
        async function deleteContact(id) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_contact&id=${encodeURIComponent(id)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    contacts = contacts.filter(c => c.id !== id);
                    localStorage.setItem('contacts', JSON.stringify(contacts));
                    updateContacts();
                    showNotification('Contact deleted successfully', 'success');
                } else {
                    showNotification('Failed to delete contact', 'error');
                }
            } catch (error) {
                console.error('Delete contact error:', error);
                showNotification('Failed to delete contact', 'error');
            }
        }
        
        // Search contacts
        document.getElementById('contactSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('#contactsList .contact-item');
            
            items.forEach(item => {
                const name = item.querySelector('.fw-bold').textContent.toLowerCase();
                const phone = item.querySelector('.text-muted').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Start conference call
        function startConferenceCall() {
            showNotification('Conference call feature coming soon!', 'info');
        }
        
        // Show keyboard shortcuts
        function showShortcuts() {
            const hint = document.getElementById('shortcutsHint');
            hint.classList.add('show');
            
            setTimeout(() => {
                hint.classList.remove('show');
            }, 5000);
        }
        
        // Export data
        function exportData() {
            const data = {
                callLogs: callLogs,
                smsHistory: smsHistory,
                contacts: contacts
            };
            
            const dataStr = JSON.stringify(data, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = `twilio_data_${new Date().toISOString().slice(0,10)}.json`;
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
            
            showNotification('Data exported successfully', 'success');
        }
        
        // Clear data
        function clearData() {
            if (confirm('Are you sure you want to clear all data? This action cannot be undone.')) {
                localStorage.removeItem('callLogs');
                localStorage.removeItem('smsHistory');
                localStorage.removeItem('contacts');
                
                callLogs = [];
                smsHistory = [];
                contacts = [];
                
                updateCallLogs();
                updateSMSHistory();
                updateContacts();
                updateStats();
                
                showNotification('All data cleared successfully', 'success');
            }
        }
        
        // Show about modal
        function showAbout() {
            const modal = new bootstrap.Modal(document.getElementById('aboutModal'));
            modal.show();
        }
        
        // Dialer Functions
        function dialNumber(num) {
            const input = document.getElementById('dialNumber');
            input.value += num;
            updateCallButton();
            
            // Play sound if enabled
            if (soundEnabled) {
                const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-button-press-643.mp3');
                audio.volume = 0.2;
                audio.play().catch(e => console.log('Audio play failed:', e));
            }
        }
        
        function dialContact(number, name) {
            document.getElementById('dialNumber').value = number;
            document.getElementById('callerAvatar').textContent = name.charAt(0);
            document.getElementById('callerName').textContent = name;
            document.getElementById('callerNumber').textContent = number;
            updateCallButton();
        }
        
        function clearDialNumber() {
            document.getElementById('dialNumber').value = '';
            updateCallButton();
        }
        
        function backspaceDialNumber() {
            const input = document.getElementById('dialNumber');
            input.value = input.value.slice(0, -1);
            updateCallButton();
        }
        
        function updateCallButton() {
            const number = document.getElementById('dialNumber').value;
            const isConnected = device && device.status() === 'ready';
            document.getElementById('callButton').disabled = !number || !isConnected;
        }
        
        function openKeypad() {
            const modal = new bootstrap.Modal(document.getElementById('keypadModal'));
            modal.show();
        }
        
        // Twilio Functions
        async function connectTwilio() {
            try {
                // Show loading state
                document.getElementById('connectBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
                document.getElementById('connectBtn').disabled = true;
                
                // Get token from server
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=generate_token'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to generate token');
                }
                
                if (!data.token) {
                    throw new Error('No token received');
                }
                
                // Initialize device
                device = new Twilio.Device(data.token, {
                    codecPreferences: ['opus', 'pcmu'],
                    fakeLocalDTMF: true,
                    enableRingingState: true
                });
                
                // Setup event listeners
                device.on('ready', () => {
                    updateStatus('ready', 'Connected to Twilio');
                    updateCallButton();
                    showNotification('Connected to Twilio successfully', 'success');
                });
                
                device.on('error', (error) => {
                    console.error('Device error:', error);
                    updateStatus('error', error.message);
                    showNotification('Connection error: ' + error.message, 'error');
                    
                    // Reset button state
                    document.getElementById('connectBtn').innerHTML = '<i class="fas fa-play-circle me-2"></i>Connect';
                    document.getElementById('connectBtn').disabled = false;
                });
                
                device.on('connect', (connection) => {
                    handleConnectedCall(connection);
                });
                
                device.on('disconnect', (connection) => {
                    handleDisconnectedCall(connection);
                });
                
                device.on('incoming', (connection) => {
                    handleIncomingCall(connection);
                });
                
                device.on('cancel', () => {
                    console.log('Incoming call canceled');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'));
                    if (modal) modal.hide();
                });
                
                device.on('offline', () => {
                    updateStatus('offline', 'Disconnected');
                });
                
                // Register device
                device.register();
                
            } catch (error) {
                console.error('Connection error:', error);
                showNotification('Failed to connect: ' + error.message, 'error');
                
                // Reset button state
                document.getElementById('connectBtn').innerHTML = '<i class="fas fa-play-circle me-2"></i>Connect';
                document.getElementById('connectBtn').disabled = false;
            }
        }
        
        function disconnectTwilio() {
            if (device) {
                device.unregister();
                device.destroy();
                device = null;
            }
            updateStatus('offline', 'Disconnected');
            document.getElementById('connectBtn').disabled = false;
            document.getElementById('disconnectBtn').disabled = true;
            updateCallButton();
            showNotification('Disconnected from Twilio', 'info');
        }
        
        function updateStatus(status, message) {
            const indicator = document.getElementById('statusIndicator');
            const text = document.getElementById('statusText');
            
            indicator.className = `status-indicator status-${status}`;
            text.textContent = message;
            
            // Update button states
            document.getElementById('connectBtn').disabled = status === 'ready';
            document.getElementById('disconnectBtn').disabled = status !== 'ready';
            
            // Update connection quality
            updateConnectionQuality(status === 'ready' ? 4 : 0);
        }
        
        function updateConnectionQuality(quality) {
            connectionQuality = quality;
            const level = document.getElementById('qualityLevel');
            const text = document.getElementById('qualityText');
            
            let qualityClass = 'quality-poor';
            let qualityText = 'Poor';
            
            if (quality >= 4) {
                qualityClass = 'quality-excellent';
                qualityText = 'Excellent';
            } else if (quality >= 2) {
                qualityClass = 'quality-good';
                qualityText = 'Good';
            }
            
            level.className = `quality-level ${qualityClass}`;
            level.style.width = (quality * 25) + '%';
            text.textContent = `Connection quality: ${qualityText}`;
        }
        
        function handleConnectedCall(connection) {
            activeCall = connection;
            
            // Update UI
            document.getElementById('activeCallCard').style.display = 'block';
            document.getElementById('callButton').disabled = true;
            document.getElementById('endCallButton').disabled = false;
            
            // Show voice visualization
            document.getElementById('voiceVisualizer').style.display = 'flex';
            
            startCallTimer();
            
            // Setup call event listeners
            connection.on('accept', () => {
                console.log('Call accepted');
                updateConnectionQuality(4);
                showNotification('Call connected', 'success');
            });
            
            connection.on('disconnect', () => {
                handleDisconnectedCall(connection);
            });
            
            connection.on('error', (error) => {
                console.error('Call error:', error);
                updateConnectionQuality(1);
                showNotification('Call error: ' + error.message, 'error');
            });
        }
        
        function handleIncomingCall(connection) {
            activeCall = connection;
            
            // Show incoming call modal
            const callerNumber = connection.parameters.From || 'Unknown';
            document.getElementById('incomingCallerNumber').textContent = callerNumber;
            
            const modal = new bootstrap.Modal(document.getElementById('incomingCallModal'));
            modal.show();
            
            // Play ringtone if enabled
            if (soundEnabled) {
                playNotificationSound('incoming');
            }
            
            // Show notification
            showNotification(`Incoming call from ${callerNumber}`, 'info');
            
            // Auto-decline after 30 seconds
            setTimeout(() => {
                if (activeCall === connection) {
                    declineCall();
                    modal.hide();
                    addCallLog('Missed', callerNumber, '00:00');
                }
            }, 30000);
        }
        
        function handleDisconnectedCall(connection) {
            const duration = stopCallTimer();
            const caller = connection.parameters.From || 'Unknown';
            const direction = connection.parameters.Direction || 'outgoing';
            
            addCallLog(direction === 'incoming' ? 'Incoming' : 'Outgoing', caller, duration);
            
            // Hide voice visualization
            document.getElementById('voiceVisualizer').style.display = 'none';
            
            resetCallUI();
            showNotification('Call ended', 'info');
        }
        
        async function makeCall() {
            const number = document.getElementById('dialNumber').value;
            if (!number || !device) return;
            
            try {
                // You can also use server-side call initiation
                const params = {
                    To: number
                };
                
                const connection = device.connect(params);
                handleConnectedCall(connection);
                
                // Update caller info
                document.getElementById('callerNumber').textContent = number;
                
                // Try to find contact name
                const contact = contacts.find(c => c.phone === number);
                if (contact) {
                    document.getElementById('callerName').textContent = contact.name;
                    document.getElementById('callerAvatar').textContent = contact.name.charAt(0);
                } else {
                    document.getElementById('callerName').textContent = 'Unknown';
                    document.getElementById('callerAvatar').textContent = '?';
                }
                
            } catch (error) {
                console.error('Call failed:', error);
                showNotification('Call failed: ' + error.message, 'error');
            }
        }
        
        function answerCall() {
            if (!activeCall) return;
            
            activeCall.accept();
            document.getElementById('endCallButton').disabled = false;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'));
            if (modal) modal.hide();
            
            startCallTimer();
            
            // Update caller info
            const callerNumber = activeCall.parameters.From || 'Unknown';
            document.getElementById('callerNumber').textContent = callerNumber;
            
            // Try to find contact name
            const contact = contacts.find(c => c.phone === callerNumber);
            if (contact) {
                document.getElementById('callerName').textContent = contact.name;
                document.getElementById('callerAvatar').textContent = contact.name.charAt(0);
            } else {
                document.getElementById('callerName').textContent = 'Unknown';
                document.getElementById('callerAvatar').textContent = '?';
            }
            
            // Show voice visualization
            document.getElementById('voiceVisualizer').style.display = 'flex';
        }
        
        function declineCall() {
            if (!activeCall) return;
            
            activeCall.reject();
            activeCall = null;
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('incomingCallModal'));
            if (modal) modal.hide();
        }
        
        function endCall() {
            if (!activeCall) return;
            
            activeCall.disconnect();
            activeCall = null;
            
            document.getElementById('endCallButton').disabled = true;
            document.getElementById('callButton').disabled = false;
            document.getElementById('activeCallCard').style.display = 'none';
        }
        
        function toggleMute() {
            if (!activeCall) return;
            
            isMuted = !isMuted;
            const btn = document.getElementById('muteBtn');
            
            if (isMuted) {
                activeCall.mute(true);
                btn.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                btn.className = 'btn btn-danger w-100 py-3 ripple';
                showNotification('Microphone muted', 'info');
            } else {
                activeCall.mute(false);
                btn.innerHTML = '<i class="fas fa-microphone"></i>';
                btn.className = 'btn btn-outline-secondary w-100 py-3 ripple';
                showNotification('Microphone unmuted', 'info');
            }
        }
        
        function toggleHold() {
            if (!activeCall) return;
            
            isOnHold = !isOnHold;
            const btn = document.getElementById('holdBtn');
            
            if (isOnHold) {
                activeCall.hold();
                btn.innerHTML = '<i class="fas fa-play"></i>';
                btn.className = 'btn btn-warning w-100 py-3 ripple';
                showNotification('Call on hold', 'info');
            } else {
                activeCall.unhold();
                btn.innerHTML = '<i class="fas fa-pause"></i>';
                btn.className = 'btn btn-outline-warning w-100 py-3 ripple';
                showNotification('Call resumed', 'info');
            }
        }
        
        function sendDigit(digit) {
            if (!activeCall) return;
            
            activeCall.sendDigits(digit);
            
            // Visual feedback
            const status = document.getElementById('callStatus');
            const original = status.innerHTML;
            status.innerHTML = `<i class="fas fa-broadcast-tower me-2"></i>Sent: ${digit}`;
            
            setTimeout(() => {
                status.innerHTML = original;
            }, 1000);
            
            // Play sound if enabled
            if (soundEnabled) {
                const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-button-press-643.mp3');
                audio.volume = 0.2;
                audio.play().catch(e => console.log('Audio play failed:', e));
            }
        }
        
        async function sendSMS() {
            const to = document.getElementById('smsTo').value;
            const message = document.getElementById('smsMessage').value;
            
            if (!to || !message) {
                showNotification('Please enter both phone number and message', 'error');
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_sms&to=${encodeURIComponent(to)}&message=${encodeURIComponent(message)}`
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    addSMS('sms-sent', to, message);
                    document.getElementById('smsMessage').value = '';
                    showNotification('SMS sent successfully!', 'success');
                } else {
                    showNotification('Failed to send SMS: ' + result.error, 'error');
                }
            } catch (error) {
                console.error('SMS error:', error);
                showNotification('Failed to send SMS', 'error');
            }
        }
        
        function startCallTimer() {
            callStartTime = new Date();
            callTimer = setInterval(() => {
                const now = new Date();
                const diff = Math.floor((now - callStartTime) / 1000);
                const minutes = Math.floor(diff / 60).toString().padStart(2, '0');
                const seconds = (diff % 60).toString().padStart(2, '0');
                document.getElementById('callTimer').textContent = `${minutes}:${seconds}`;
            }, 1000);
        }
        
        function stopCallTimer() {
            if (callTimer) {
                clearInterval(callTimer);
                callTimer = null;
            }
            
            if (callStartTime) {
                const now = new Date();
                const diff = Math.floor((now - callStartTime) / 1000);
                const minutes = Math.floor(diff / 60).toString().padStart(2, '0');
                const seconds = (diff % 60).toString().padStart(2, '0');
                callStartTime = null;
                return `${minutes}:${seconds}`;
            }
            return '00:00';
        }
        
        function resetCallUI() {
            document.getElementById('callTimer').textContent = '00:00';
            document.getElementById('dialNumber').value = '';
            document.getElementById('activeCallCard').style.display = 'none';
            document.getElementById('callButton').disabled = false;
            document.getElementById('endCallButton').disabled = true;
            
            // Reset mute/hold buttons
            document.getElementById('muteBtn').innerHTML = '<i class="fas fa-microphone"></i>';
            document.getElementById('muteBtn').className = 'btn btn-outline-secondary w-100 py-3 ripple';
            document.getElementById('holdBtn').innerHTML = '<i class="fas fa-pause"></i>';
            document.getElementById('holdBtn').className = 'btn btn-outline-warning w-100 py-3 ripple';
            
            isMuted = false;
            isOnHold = false;
            updateCallButton();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ignore if user is typing in an input field
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            switch(e.key.toLowerCase()) {
                case 'c':
                    if (!device || device.status() !== 'ready') {
                        connectTwilio();
                    }
                    break;
                case 'd':
                    if (device && device.status() === 'ready') {
                        disconnectTwilio();
                    }
                    break;
                case 'm':
                    if (!document.getElementById('callButton').disabled) {
                        makeCall();
                    }
                    break;
                case 'e':
                    if (!document.getElementById('endCallButton').disabled) {
                        endCall();
                    }
                    break;
                case 's':
                    sendSMS();
                    break;
                case 'k':
                    openKeypad();
                    break;
                case 't':
                    toggleTheme();
                    break;
                case 'escape':
                    // Close any open modals
                    document.querySelectorAll('.modal.show').forEach(modal => {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    });
                    break;
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            initUI();
            
            // Auto-connect if we have device capability
            setTimeout(() => {
                if (typeof Twilio !== 'undefined') {
                    // Auto-connect logic here if needed
                }
            }, 1000);
            
            // Allow Enter key to make call
            document.getElementById('dialNumber').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    makeCall();
                }
            });
            
            // Add ripple effect to buttons
            document.querySelectorAll('.ripple').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple-effect');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>
</body>
</html>