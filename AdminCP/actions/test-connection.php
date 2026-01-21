<?php
// 1. Suppress HTML errors to prevent JSON crashes
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

// 2. Security Check: Only allow logged-in admins
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    echo json_encode(['connection' => false, 'error' => 'Access Denied']); 
    exit; 
}

// 3. Driver Check
if (!function_exists('sqlsrv_connect')) {
    echo json_encode([
        'connection' => false, 
        'error' => 'CRITICAL: The "php_sqlsrv" driver is not loaded. Please restart XAMPP.'
    ]);
    exit;
}

require_once '../../config.php'; //

// Decryption function
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$server = $_POST['server'] ?? '';
$settings_file = '../../Configuration/settings.json'; //

if (!file_exists($settings_file)) {
    echo json_encode(['connection' => false, 'error' => 'Settings file not found.']);
    exit;
}

$settings = json_decode(file_get_contents($settings_file), true);

// --- DYNAMIC SERVER SELECTION ---
if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    // Use dynamic name from settings, fallback to 'MuOnline'
    $db_name = $db_config['name'] ?? 'MuOnline'; 
} elseif ($server === 'hard') {
    $db_config = $settings['database']['hard_rate'];
    // Use dynamic name from settings, fallback to 'MuOnlineEly'
    $db_name = $db_config['name'] ?? 'MuOnlineEly';
} else {
    echo json_encode(['connection' => false, 'error' => 'Invalid server type selected']);
    exit;
}

// --- CONNECTION OPTIONS (Support for Driver 18 & Below) ---
$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "LoginTimeout" => 5,
    "TrustServerCertificate" => 1, // Required for ODBC Driver 18+
    "Encrypt" => 0                 // broadens compatibility for older setups
];

try {
    $conn = sqlsrv_connect($db_config['host'], $connectionOptions);

    if ($conn) {
        // Connection successful, check specific tables
        $tables_to_check = ['MEMB_INFO', 'MEMB_STAT', 'Guild', 'Character'];
        $table_results = [];
        
        foreach ($tables_to_check as $table) {
            $sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
            $stmt = sqlsrv_query($conn, $sql, [$table]);
            $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : ['count' => 0];
            $table_results[$table] = ($row['count'] > 0);
        }
        
        sqlsrv_close($conn);
        echo json_encode([
            'connection' => true,
            'tables' => $table_results
        ]);
    } else {
        // Connection failed, extract error message
        $errors = sqlsrv_errors();
        $error_msg = ($errors) ? $errors[0]['message'] : 'Unknown error';
        
        if (strpos($error_msg, 'Login failed') !== false) {
             $error_msg = "Login failed. Please check User/Password in Database Settings.";
        }
        
        echo json_encode([
            'connection' => false,
            'error' => 'Connection Failed: ' . $error_msg
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'connection' => false,
        'error' => 'System Exception: ' . $e->getMessage()
    ]);
}
?>