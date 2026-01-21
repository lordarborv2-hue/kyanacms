<?php
header('Content-Type: application/json');
require_once '../config.php';
$settings = json_decode(file_get_contents('settings.json'), true);

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$serverType = $_GET['server'] ?? '';
if ($serverType === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = $db_config['name'] ?? 'MuOnline'; // Dynamic
} elseif ($serverType === 'hard') {
    $db_config = $settings['database']['hard_rate'];
    $db_name = $db_config['name'] ?? 'MuOnlineEly'; // Dynamic
} else {
    echo json_encode(['error' => 'Invalid server.']);
    exit;
}

$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
];

$conn = sqlsrv_connect($db_config['host'], $connectionOptions);
$response = ['online' => 'N/A'];

if ($conn) {
    $stmt = sqlsrv_query($conn, "SELECT COUNT(*) as online_count FROM MEMB_STAT WHERE ConnectStat = 1");
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $response['online'] = $row['online_count'] ?? 0;
    }
    sqlsrv_close($conn);
}
echo json_encode($response);
?>