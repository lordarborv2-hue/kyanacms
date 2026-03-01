<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);
require_once '../config.php';

if (!isset($_SESSION['user_loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); 
    exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);

// Bulletproof Session Check to ensure it uses the correct database and pricing array
$user_sess = strtolower($_SESSION['user_server'] ?? '');
$server_key = (strpos($user_sess, 'hard') !== false || strpos($user_sess, '2') !== false) ? 'hard_rate' : 'mid_rate';

$db_config = $settings['database'][$server_key];

function decrypt_pass($g, $k) {
    list($d, $i) = explode('::', base64_decode($g), 2);
    return openssl_decrypt($d, ENCRYPTION_CIPHER, $k, 0, $i);
}

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'], "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1
]);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']); 
    exit;
}

$sql = "SELECT * FROM WebshopItems WHERE IsActive = 1 ORDER BY ItemType, ItemIndex";
$stmt = sqlsrv_query($conn, $sql);

$items = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $items[] = $row;
    }
}

// Fetch the specific pricing for the active server
$pricing = $settings['webshop'][$server_key] ?? [
    'price_level' => 10, 'price_exc' => 50, 'price_luck_skill' => 25, 
    'price_380' => 100, 'price_harmony' => 100, 'price_socket' => 50, 'price_ancient' => 100
];

echo json_encode([
    'success' => true, 
    'items' => $items, 
    'pricing' => $pricing,
    'server_loaded' => $server_key
]);

sqlsrv_close($conn);
?>