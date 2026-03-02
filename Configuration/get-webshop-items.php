<?php
header('Content-Type: application/json');
session_start();
error_reporting(0); 
require_once '../config.php'; // Inherits decrypt_data globally

if (!isset($_SESSION['user_loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); 
    exit;
}

$settings = json_decode(file_get_contents('settings.json'), true);

// Bulletproof Session Check
$user_sess = strtolower($_SESSION['user_server'] ?? '');
$server_key = (strpos($user_sess, 'hard') !== false || strpos($user_sess, '2') !== false) ? 'hard_rate' : 'mid_rate';

$db_config = $settings['database'][$server_key];

// REMOVED: Local decrypt_pass function to prevent "Cannot redeclare" error

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'], 
    "Uid" => $db_config['user'],
    "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY), // Using global function
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
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