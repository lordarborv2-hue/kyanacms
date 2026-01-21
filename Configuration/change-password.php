<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '../config.php';

if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Auth required.']); exit;
}

$old_pass = $_POST['old_password'] ?? '';
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_password'] ?? '';
$username = $_SESSION['user_id'];
$server = $_SESSION['user_server'];

if (empty($old_pass) || empty($new_pass)) {
    echo json_encode(['success' => false, 'message' => 'All fields required.']); exit;
}
if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']); exit;
}
if (strlen($new_pass) < 4 || strlen($new_pass) > 10) { // Standard MU limit
    echo json_encode(['success' => false, 'message' => 'Password must be 4-10 characters.']); exit;
}

// Connect to DB (Same logic as user-action)
$settings = json_decode(file_get_contents('settings.json'), true);
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = $db_config['name'];
} else {
    $db_config = $settings['database']['hard_rate'];
    $db_name = $db_config['name'];
}

$conn = sqlsrv_connect($db_config['host'], [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
]);

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB Connection Failed']); exit; }

// 1. Verify Old Password
$sql = "SELECT memb__pwd FROM MEMB_INFO WHERE memb___id = ?";
$stmt = sqlsrv_query($conn, $sql, [$username]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row || $row['memb__pwd'] !== $old_pass) {
    echo json_encode(['success' => false, 'message' => 'Incorrect Old Password.']); exit;
}

// 2. Update Password
$updateSql = "UPDATE MEMB_INFO SET memb__pwd = ? WHERE memb___id = ?";
$r = sqlsrv_query($conn, $updateSql, [$new_pass, $username]);

if ($r) {
    echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
sqlsrv_close($conn);
?>