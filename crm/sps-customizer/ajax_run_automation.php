<?php


error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/activity_log.php';

require_once 'PHPMailer/PHPMailerAutoload.php';


header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userid = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';

// Get client_id from POST (passed from JavaScript)
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

if ($client_id === 0) {
    echo json_encode(['success' => false, 'message' => 'No client specified']);
    exit;
}

// Verify client exists and get details
$client_query = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
if (mysqli_num_rows($client_query) === 0) {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
    exit;
}

$client = mysqli_fetch_assoc($client_query);
$client_name = trim($client['name'] . ' ' . $client['mname'] . ' ' . $client['lname']);
$client_email = $client['prm_email'];
$business_name = $client['bus_name'] ?: $client_name;

// Validate email
if (empty($client_email) || !filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Client has no valid email address']);
    exit;
}

// Initialize PHPMailer
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'invoice@syedpro.com';
    $mail->Password = 'bgtqpxznmghqwkrc';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('invoice@syedpro.com', 'Syed Professional Services');
    $mail->isHTML(true);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Mailer setup failed: ' . $e->getMessage()]);
    exit;
}

$current_year = date('Y');
$current_month = date('n');
$sent_count = 0;
$error_count = 0;
$details = [];

// Get ONLY this client's banks with missing statements
$missing_banks = [];

$banks_query = mysqli_query($con, "
    SELECT cb.*, 
        GROUP_CONCAT(DISTINCT bs.statement_month ORDER BY bs.statement_month) as received_months
    FROM client_banks cb
    LEFT JOIN bank_statements bs ON cb.bank_id = bs.bank_id 
        AND bs.statement_year = '$current_year' 
        AND bs.received = 1
    WHERE cb.member_id = '$client_id' AND cb.is_active = 1
    GROUP BY cb.bank_id
");

while ($bank = mysqli_fetch_assoc($banks_query)) {
    $received = $bank['received_months'] ? explode(',', $bank['received_months']) : [];
    $missing = [];

    $check_months = ($current_year == date('Y')) ? $current_month : 12;

    for ($m = 1; $m <= $check_months; $m++) {
        if (!in_array($m, $received)) {
            $missing[] = $m;
        }
    }

    if (!empty($missing)) {
        $missing_banks[] = [
            'bank_id' => $bank['bank_id'],
            'bank_name' => $bank['bank_name'],
            'account_number' => $bank['account_number'],
            'account_type' => $bank['account_type'],
            'missing_months' => $missing
        ];
    }
}

if (empty($missing_banks)) {
    echo json_encode([
        'success' => true,
        'sent' => 0,
        'errors' => 0,
        'message' => 'No missing statements found for this client. All caught up!',
        'details' => []
    ]);
    exit;
}

try {
    $mail->clearAddresses();
    $mail->addAddress($client_email, $client_name);
    $mail->Subject = "Action Required: Missing Bank Statements - $business_name";

    $body = buildEmailTemplate($client_name, $business_name, $missing_banks, $current_year);
    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n", "\n"], $body));

    if ($mail->send()) {
        $sent_count = 1;

        foreach ($missing_banks as $bank_data) {
            $missing_names = array_map(function ($m) {
                $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return $months[$m];
            }, $bank_data['missing_months']);

            logActivity(
                $con,
                $userid,
                'EMAIL',
                'bank_statements',
                $bank_data['bank_id'],
                "Sent reminder to $client_email for missing statements: " . implode(', ', $missing_names)
            );
        }

        $details[] = [
            'client' => $client_name,
            'email' => $client_email,
            'banks' => count($missing_banks),
            'status' => 'sent'
        ];

    } else {
        throw new Exception($mail->ErrorInfo);
    }

} catch (Exception $e) {
    $error_count = 1;
    $details[] = [
        'client' => $client_name,
        'email' => $client_email,
        'error' => $e->getMessage(),
        'status' => 'failed'
    ];
    error_log("Failed to send to $client_email: " . $e->getMessage());
}

logActivity(
    $con,
    $userid,
    'CREATE',
    'automation_run',
    $client_id,
    "Manual automation for client $client_id: $sent_count sent, $error_count errors"
);

echo json_encode([
    'success' => true,
    'sent' => $sent_count,
    'errors' => $error_count,
    'client_name' => $client_name,
    'details' => $details,
    'message' => $sent_count > 0
        ? "Reminder sent successfully to $client_name"
        : "Failed to send reminder to $client_name"
]);

function buildEmailTemplate($client_name, $business_name, $missing_banks, $year)
{
    $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
            
            <div style='background: linear-gradient(135deg, #6FD943 0%, #5bc73a 100%); padding: 30px; text-align: center;'>
                <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>Missing Bank Statements</h2>
                <p style='color: #e8f5e9; margin: 10px 0 0 0;'>Action Required for Tax Year $year</p>
            </div>
            
            <div style='padding: 30px;'>
                <p style='font-size: 16px; margin-bottom: 20px;'>Dear <strong>$client_name</strong>,</p>
                
                <p style='margin-bottom: 20px;'>We are preparing your financial records for <strong>Tax Year $year</strong>. Our records indicate that we are missing the following bank statements for <strong>$business_name</strong>:</p>
                
                <div style='background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;'>";

    foreach ($missing_banks as $index => $bank) {
        $last4 = substr($bank['account_number'], -4);
        $missing_names = array_map(function ($m) use ($months) {
            return $months[$m];
        }, $bank['missing_months']);

        $html .= "
                    <div style='" . ($index > 0 ? "margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;" : "") . "'>
                        <div style='display: flex; align-items: center; margin-bottom: 8px;'>
                            <span style='background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 10px;'>MISSING</span>
                            <strong style='font-size: 16px; color: #333;'>{$bank['bank_name']}</strong>
                        </div>
                        <div style='color: #6c757d; font-size: 14px; margin-left: 4px; margin-bottom: 8px;'>
                            {$bank['account_type']} Account •••• $last4
                        </div>
                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; border-radius: 4px;'>
                            <span style='color: #856404; font-weight: 600;'>Missing Months:</span><br>
                            <span style='color: #856404;'>" . implode(', ', $missing_names) . "</span>
                        </div>
                    </div>";
    }

    $html .= "
                </div>
                
                <p style='margin: 20px 0;'>Please upload these documents through your client portal or reply to this email with the statements attached.</p>
                
                <div style='background: #e8f5e9; border-left: 4px solid #6FD943; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                    <p style='margin: 0; color: #2e7d32; font-size: 14px;'>
                        <strong>Need help?</strong> Contact your account manager or call us at (555) 123-4567
                    </p>
                </div>
                
                <p style='margin-top: 30px;'>Best regards,<br>
                <strong style='color: #6FD943;'>Syed Professional Services</strong><br>
                <span style='color: #6c757d; font-size: 14px;'>Accounting & Tax Services</span></p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d; margin: 0;'>
                    This is an automated reminder. Please do not reply directly to this email.<br>
                    Use your client portal or contact your account manager.
                </p>
            </div>
        </div>
    </body>
    </html>";

    return $html;
}
?>