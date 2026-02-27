<?php
header('Content-Type: application/json');
session_start();
error_reporting(0);
require_once '../config.php';

$settings = json_decode(file_get_contents('settings.json'), true);

$server = $_SESSION['user_server'] ?? 'mid';
$server_key = ($server === 'mid') ? 'mid_rate' : 'hard_rate';
$db_config = $settings['database'][$server_key]; 

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

if (empty($db_config['host'])) {
    echo json_encode(['success' => false, 'error' => 'Database not configured for this server.']);
    exit;
}

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_config['name'] ?? 'MuOnline', 
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "TrustServerCertificate" => 1, "Encrypt" => 0
]);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Fetch all active items, including the new AllowAncient column
$sql = "SELECT 
            ItemType, ItemIndex, ItemName, BasePrice, 
            AllowExc, AllowLevel, Allow380, AllowHarmony, AllowSocket, AllowAncient,
            AncName1, AncName2,
            MaxExc, MaxSocket, AllowLuck, AllowSkill
        FROM WebshopItems 
        WHERE IsActive = 1 
        ORDER BY ItemType, ItemIndex";
        
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch items']);
    exit;
}

$items = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $items[] = $row;
}

// Pass the specific server's multipliers
$pricing = $settings['webshop'][$server_key] ?? $settings['webshop'] ?? [];

echo json_encode(['success' => true, 'items' => $items, 'pricing' => $pricing]);
sqlsrv_close($conn);
?>