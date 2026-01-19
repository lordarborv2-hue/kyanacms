<?php
// ================================================
// REGISTRATION BACKEND
// ================================================

header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load configuration
require_once '../config.php';

// Load settings
$settings_file = 'settings.json';
if (!file_exists($settings_file)) {
    echo json_encode(['success' => false, 'message' => 'Configuration file not found.']);
    exit;
}

$settings = json_decode(file_get_contents($settings_file), true);

// Decryption function
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get form data
$server = $_POST['server'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($server) || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate server selection
if (!in_array($server, ['mid', 'hard'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid server selection.']);
    exit;
}

// Validate username format (4-10 characters, alphanumeric only)
if (!preg_match('/^[a-zA-Z0-9]{4,10}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 4-10 characters (letters and numbers only).']);
    exit;
}

// Validate password length
if (strlen($password) < 4 || strlen($password) > 20) {
    echo json_encode(['success' => false, 'message' => 'Password must be 4-20 characters.']);
    exit;
}

// Get database configuration based on server selection
if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = "MuOnlineMid";
} else {
    $db_config = $settings['database']['hard_rate'];
    $db_name = "MuOnlineTest";
}

// Database connection options
$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8"
];

$serverName = $db_config['host'];

// Connect to database
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    $errors = sqlsrv_errors();
    error_log('Database connection failed: ' . print_r($errors, true));
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Check if username already exists
$checkSql = "SELECT COUNT(*) as count FROM MEMB_INFO WHERE memb___id = ?";
$checkParams = [$username];
$checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);

if (!$checkStmt) {
    $errors = sqlsrv_errors();
    error_log('Check query failed: ' . print_r($errors, true));
    sqlsrv_close($conn);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
    exit;
}

$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
if ($row['count'] > 0) {
    sqlsrv_close($conn);
    echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose another.']);
    exit;
}

// Insert new account
// Note: In MU Online, passwords are typically stored in plain text or with simple encoding
// You may need to adjust this based on your server's password handling
$insertSql = "INSERT INTO MEMB_INFO (memb___id, memb__pwd, memb_name, sno__numb, post_code, addr_info, addr_deta, tel__numb, phon_numb, mail_addr, fpas_ques, fpas_answ, job__code, appl_days, modi_days, out__days, true_days, mail_chek, bloc_code, ctl1_code) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), GETDATE(), GETDATE(), ?, ?, ?)";

$insertParams = [
    $username,              // memb___id (username)
    $password,              // memb__pwd (password)
    $username,              // memb_name (display name, same as username)
    '0000000000000',        // sno__numb (social security - default)
    '000000',               // post_code (postal code - default)
    'N/A',                  // addr_info (address - default)
    'N/A',                  // addr_deta (detailed address - default)
    '000-0000-0000',        // tel__numb (telephone - default)
    '000-0000-0000',        // phon_numb (phone - default)
    'noemail@example.com',  // mail_addr (email - default)
    'N/A',                  // fpas_ques (password question - default)
    'N/A',                  // fpas_answ (password answer - default)
    '0',                    // job__code (job code - default)
    0,                      // mail_chek (email verified - 0 = no)
    0,                      // bloc_code (blocked - 0 = not blocked)
    0                       // ctl1_code (control code - 0 = normal)
];

$insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);

if (!$insertStmt) {
    $errors = sqlsrv_errors();
    error_log('Insert query failed: ' . print_r($errors, true));
    sqlsrv_close($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
    exit;
}

// Success!
sqlsrv_close($conn);
echo json_encode([
    'success' => true,
    'message' => 'Account registered successfully!',
    'username' => $username,
    'server' => $server
]);
exit;
?>
