<?php
header('Content-Type: application/json');

// --- NEW DYNAMIC CONNECTION ---
require_once '../config.php'; // Get secret key
$settings = json_decode(file_get_contents('settings.json'), true);

// Decryption function
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$serverType = $_GET['server'] ?? '';
if ($serverType === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = "MuOnline";
} elseif ($serverType === 'hard') {
    $db_config = $settings['database']['hard_rate'];
    $db_name = "MuOnlineEly";
} else {
    echo json_encode(['error' => 'Invalid server specified.']);
    exit;
}

$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8"
];
$serverName = $db_config['host'];
// --- END DYNAMIC CONNECTION ---

$conn = sqlsrv_connect($serverName, $connectionOptions);
$response = [];

if ($conn) {
    $sql = "SELECT COUNT(*) as online_count FROM MEMB_STAT WHERE ConnectStat = 1";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $response['online'] = $row['online_count'] ?? 0;
    } else {
        $response['online'] = 'N/A';
    }
    sqlsrv_close($conn);
} else {
    $response['online'] = 'N/A';
}

echo json_encode($response);
?>