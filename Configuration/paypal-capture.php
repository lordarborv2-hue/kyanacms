<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);

require_once '../config.php'; // Included to grab decryption tools

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$server_key = ($_SESSION['user_server'] === 'mid') ? 'mid_rate' : 'hard_rate';
$paypal_conf = $settings['paypal'][$server_key] ?? $settings['paypal'];

if (empty($paypal_conf['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'PayPal is disabled.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderID = $input['orderID'] ?? '';

if (!$orderID) { echo json_encode(['success' => false, 'message' => 'No Order ID provided.']); exit; }

// Decrypt the secure PayPal API Keys
$client_id = decrypt_data($paypal_conf['client_id'], ENCRYPTION_KEY);
$secret = decrypt_data($paypal_conf['secret'], ENCRYPTION_KEY); // <--- Check this!
$env = ($paypal_conf['mode'] === 'sandbox') ? 'api-m.sandbox.paypal.com' : 'api-m.paypal.com';

// 1. Get Access Token
$ch = curl_init("https://$env/v1/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$client_id:$secret"); // Must be plain text here
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
$response = curl_exec($ch);
$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'PayPal Auth Error.']); exit;
}
$access_token = $token_data['access_token'];

// 2. Capture the Payment
$ch = curl_init("https://$env/v2/checkout/orders/$orderID/capture");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $access_token"
]);
$capture_response = curl_exec($ch);
$capture_data = json_decode($capture_response, true);

if (isset($capture_data['status']) && $capture_data['status'] === 'COMPLETED') {
    // 3. Security: Calculate credits based on what was ACTUALLY paid, ignoring the frontend.
    $actual_paid = (float)$capture_data['purchase_units'][0]['payments']['captures'][0]['amount']['value'];
    $rate = (int)($paypal_conf['rate'] ?? 100);
    $credits_to_add = floor($actual_paid * $rate);

    // 4. Update Database
    $username = $_SESSION['user_id'];
    $db_config = $settings['database'][$server_key];

    // Connect using global decrypt_data function instead of the old inline one
    $conn = sqlsrv_connect($db_config['host'], [
        "Database" => $db_config['name'], "Uid" => $db_config['user'],
        "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY),
        "TrustServerCertificate" => 1, "Encrypt" => 0
    ]);

    if ($conn) {
        $checkSql = "IF NOT EXISTS (SELECT 1 FROM WebCredits WHERE memb___id = ?) INSERT INTO WebCredits (memb___id, credits) VALUES (?, 0)";
        sqlsrv_query($conn, $checkSql, [$username, $username]);
        
        sqlsrv_query($conn, "UPDATE WebCredits SET credits = credits + ? WHERE memb___id = ?", [$credits_to_add, $username]);
        sqlsrv_close($conn);

        echo json_encode(['success' => true, 'message' => "Payment successful! You received $credits_to_add WebCredits.", 'credits' => $credits_to_add]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment received, but database error occurred. Contact Admin.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Payment was not completed.']);
}
?>