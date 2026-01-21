<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

$settings_file = 'settings.json';
if (!file_exists($settings_file)) { echo json_encode(['success' => false, 'message' => 'Config not found.']); exit; }
$settings = json_decode(file_get_contents($settings_file), true);

function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit; }

$server = $_POST['server'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($server) || empty($username) || empty($password)) { echo json_encode(['success' => false, 'message' => 'All fields required.']); exit; }

// Select Database
if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = $db_config['name'] ?? 'MuOnline';
    $server_label = $settings['mid_rate_server']['name'];
} elseif ($server === 'hard') {
    $db_config = $settings['database']['hard_rate'];
    $db_name = $db_config['name'] ?? 'MuOnlineEly';
    $server_label = $settings['hard_rate_server']['name'];
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid server.']); exit;
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

if (!$conn) { echo json_encode(['success' => false, 'message' => 'Database connection failed.']); exit; }

// Verify Login
$sql = "SELECT memb___id FROM MEMB_INFO WHERE memb___id = ? AND memb__pwd = ?";
$stmt = sqlsrv_query($conn, $sql, [$username, $password]);

if ($stmt && sqlsrv_has_rows($stmt)) {
    // --- START SESSION ---
    $_SESSION['user_loggedin'] = true;
    $_SESSION['user_id'] = $username;
    $_SESSION['user_server'] = $server;
    $_SESSION['user_server_label'] = $server_label;
    $_SESSION['user_last_activity'] = time(); // <--- SET TIME FOR TIMEOUT
    
    echo json_encode([
        'success' => true,
        'username' => $username,
        'redirect' => 'user-dashboard.html'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Username or Password.']);
}
sqlsrv_close($conn);
?>