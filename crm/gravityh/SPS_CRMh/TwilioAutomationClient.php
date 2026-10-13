<?php
declare(strict_types=1);

namespace SPS\Integration;

use Exception;

/**
 * TwilioAutomationClient
 * 
 * Production REST API Client for Twilio Voice, TwiML generation, and SMS/WhatsApp Messaging.
 */
class TwilioAutomationClient
{
    private string $accountSid;
    private string $authToken;
    private string $fromPhoneNumber;

    public function __construct(string $accountSid, string $authToken, string $fromPhoneNumber)
    {
        $this->accountSid = trim($accountSid);
        $this->authToken = trim($authToken);
        $this->fromPhoneNumber = trim($fromPhoneNumber);
    }

    /**
     * Checks if Twilio credentials are fully configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromPhoneNumber);
    }

    /**
     * Generates a TwiML XML voice script for outbound Twilio calls.
     */
    public function constructTwiML(string $voiceMessage, string $voice = 'alice'): string
    {
        $xmlMessage = htmlspecialchars($voiceMessage, ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<Response>' . "\n" .
               '  <Say voice="' . $voice . '">' . $xmlMessage . '</Say>' . "\n" .
               '</Response>';
    }

    /**
     * Initiates an automated Twilio voice call using TwiML XML script.
     *
     * @param string $toPhoneNumber
     * @param string $voiceScript
     * @return array Response containing status, sid, and HTTP status.
     */
    public function initiateVoiceCall(string $toPhoneNumber, string $voiceScript): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'sid' => 'CALL-SIMULATED-' . bin2hex(random_bytes(6)),
                'status' => 'simulated',
                'message' => 'Twilio API credentials not configured in settings. Event logged internally.'
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Calls.json";
        $twiml = $this->constructTwiML($voiceScript);

        $payload = [
            'From' => $this->fromPhoneNumber,
            'To' => $toPhoneNumber,
            'Twiml' => $twiml
        ];

        return $this->executeCurlRequest($url, $payload);
    }

    /**
     * Dispatches an outbound SMS message via Twilio REST API.
     *
     * @param string $toPhoneNumber
     * @param string $messageBody
     * @return array
     */
    public function sendSMS(string $toPhoneNumber, string $messageBody): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'sid' => 'SMS-SIMULATED-' . bin2hex(random_bytes(6)),
                'status' => 'simulated',
                'message' => 'Twilio API credentials not configured in settings. SMS logged internally.'
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        $payload = [
            'From' => $this->fromPhoneNumber,
            'To' => $toPhoneNumber,
            'Body' => $messageBody
        ];

        return $this->executeCurlRequest($url, $payload);
    }

    /**
     * Executes HTTP POST Request to Twilio API via cURL.
     */
    private function executeCurlRequest(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error, 'status' => 'failed'];
        }

        $json = json_decode($response, true);
        $success = ($httpCode === 201 && isset($json['sid']));

        return [
            'success' => $success,
            'sid' => $json['sid'] ?? 'FAILED',
            'status' => $json['status'] ?? 'failed',
            'http_code' => $httpCode,
            'response' => $json
        ];
    }
}
