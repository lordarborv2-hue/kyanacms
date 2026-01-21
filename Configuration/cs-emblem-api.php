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
    echo json_encode(['error' => 'Invalid server specified.']);
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
$response = ['owner_name' => 'None', 'emblem_hex' => null];

if ($conn) {
    $sql = "SELECT TOP 1 T1.OWNER_GUILD, T2.G_Mark FROM MuCastle_DATA AS T1 INNER JOIN Guild AS T2 ON T1.OWNER_GUILD = T2.G_Name WHERE T1.MAP_SVR_GROUP = 0";
    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt && sqlsrv_fetch($stmt)) {
        $owner_name = sqlsrv_get_field($stmt, 0);
        $g_mark_binary = sqlsrv_get_field($stmt, 1, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY));
        
        $response['owner_name'] = $owner_name;
        if ($g_mark_binary) {
            $response['emblem_hex'] = '0x' . bin2hex($g_mark_binary);
        }
    }
    sqlsrv_close($conn);
}
echo json_encode($response);
?>