<?php
header('Content-Type: application/json');
session_start();

// Enable error reporting to catch issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep 0 so it doesn't break JSON, we will log it instead

require_once '../config.php'; // Included to grab the decryption tools

if (!isset($_SESSION['user_loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);
$user_sess = strtolower($_SESSION['user_server'] ?? '');
$server_key = (strpos($user_sess, 'hard') !== false || strpos($user_sess, '2') !== false) ? 'hard_rate' : 'mid_rate';

$paymongo = $settings['paymongo'][$server_key] ?? null;

if (!$paymongo || empty($paymongo['enabled']) || empty($paymongo['secret_key'])) {
    echo json_encode(['success' => false, 'message' => 'PayMongo is currently disabled.']);
    exit;
}

// Decrypt the secure PayMongo API Key
$paymongo_secret = decrypt_data($paymongo['secret_key'], ENCRYPTION_KEY);

// Get requested credits and calculate PHP Cost
$credits_requested = (int)($_POST['credits'] ?? 0);
$rate = (int)($paymongo['rate'] ?? 100);

if ($credits_requested <= 0 || $rate <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount.']);
    exit;
}

$amount_php = $credits_requested / $rate;

// Minimum transaction lowered to 1 PHP
if ($amount_php < 1) {
    echo json_encode(['success' => false, 'message' => 'Minimum transaction is 1 PHP.']);
    exit;
}

// Convert to CENTS (e.g. 1 PHP = 100 cents)
$amount_cents = (int)ceil($amount_php * 100);

// Force grab the account name from Javascript
$account_id = $_POST['account'] ?? $_SESSION['username'] ?? '';

if (empty($account_id)) {
    echo json_encode(['success' => false, 'message' => 'Error: Username is missing.']);
    exit;
}

$payload = [
    'data' => [
        'attributes' => [
            'amount' => $amount_cents,
            'description' => "$credits_requested WebCredits for $account_id",
            'remarks' => "ACCOUNT:$account_id|CREDITS:$credits_requested|SERVER:$server_key"
        ]
    ]
];

$ch = curl_init('https://api.paymongo.com/v1/links');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'content-type: application/json',
    'authorization: Basic ' . base64_encode($paymongo_secret . ':') // Using decrypted key
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'CURL Error: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

if ($http_code === 200 && isset($result['data']['attributes']['checkout_url'])) {
    echo json_encode(['success' => true, 'checkout_url' => $result['data']['attributes']['checkout_url']]);
} else {
    // If PayMongo rejects the 1 PHP, we will see their exact error message here
    $error_msg = $result['errors'][0]['detail'] ?? 'Failed to generate payment link.';
    echo json_encode(['success' => false, 'message' => 'PayMongo Error: ' . $error_msg]);
}
?>