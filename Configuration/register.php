<?php
// Prevent HTML errors from breaking JSON
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load Configuration
require_once '../config.php';
$settings_file = 'settings.json';

// Validate Config File
if (!file_exists($settings_file)) {
    echo json_encode(['success' => false, 'message' => 'Configuration file not found.']);
    exit;
}

$settings = json_decode(file_get_contents($settings_file), true);

// Decryption Helper
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

// Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get Inputs
$server = $_POST['server'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validate Inputs
if (empty($server) || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!in_array($server, ['mid', 'hard'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid server selection.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9]{4,10}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 4-10 characters (letters and numbers only).']);
    exit;
}

if (strlen($password) < 4 || strlen($password) > 20) {
    echo json_encode(['success' => false, 'message' => 'Password must be 4-20 characters.']);
    exit;
}

// --- DYNAMIC DATABASE SELECTION ---
if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
} else {
    $db_config = $settings['database']['hard_rate'];
}

// Use dynamic name from settings, fallback to default
$db_name = isset($db_config['name']) ? $db_config['name'] : 'MuOnline';

// Connection Options
$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => 1, // ODBC 18 Fix
    "Encrypt" => 0
];

// Connect to SQL Server
$conn = sqlsrv_connect($db_config['host'], $connectionOptions);

if (!$conn) {
    // Log the actual error to the PHP error log for the admin to see
    error_log('Database connection failed: ' . print_r(sqlsrv_errors(), true));
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please contact support.']);
    exit;
}

// Check if username exists
$checkSql = "SELECT COUNT(*) as count FROM MEMB_INFO WHERE memb___id = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, [$username]);

if ($checkStmt === false) {
    echo json_encode(['success' => false, 'message' => 'Database error during check.']);
    sqlsrv_close($conn);
    exit;
}

$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
if ($row['count'] > 0) {
    sqlsrv_close($conn);
    echo json_encode(['success' => false, 'message' => 'Username already exists.']);
    exit;
}

// Insert New Account
$insertSql = "INSERT INTO MEMB_INFO (memb___id, memb__pwd, memb_name, sno__numb, post_code, addr_info, addr_deta, tel__numb, phon_numb, mail_addr, fpas_ques, fpas_answ, job__code, appl_days, modi_days, out__days, true_days, mail_chek, bloc_code, ctl1_code) VALUES (?, ?, ?, '0000000000000', '000000', 'N/A', 'N/A', '000-0000-0000', '000-0000-0000', 'noemail@example.com', 'N/A', 'N/A', '0', GETDATE(), GETDATE(), GETDATE(), GETDATE(), 0, 0, 0)";
$insertParams = [$username, $password, $username];

$insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

if ($insertStmt) {
    echo json_encode([
        'success' => true,
        'message' => 'Account registered successfully!',
        'username' => $username,
        'server' => $server
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create account.']);
}

sqlsrv_close($conn);
?>