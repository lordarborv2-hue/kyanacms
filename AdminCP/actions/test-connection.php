<?php
// 1. Suppress HTML errors to prevent "Unexpected token"
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

// 2. Security Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { 
    echo json_encode(['connection' => false, 'error' => 'Access Denied']); 
    exit; 
}

// 3. Driver Check
if (!function_exists('sqlsrv_connect')) {
    echo json_encode([
        'connection' => false, 
        'error' => 'CRITICAL: The "sqlsrv" driver is not loaded. Please restart XAMPP (Apache) and verify php.ini.'
    ]);
    exit;
}

require_once '../../config.php';

// Decryption function
function decrypt_pass($garbled, $key) {
    if (empty($garbled)) return '';
    list($encrypted_data, $iv) = explode('::', base64_decode($garbled), 2);
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, $key, 0, $iv);
}

$server = $_POST['server'] ?? '';
$settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);

if ($server === 'mid') {
    $db_config = $settings['database']['mid_rate'];
    $db_name = "MuOnline"; 
} elseif ($server === 'hard') {
    $db_config = $settings['database']['hard_rate'];
    $db_name = "MuOnlineEly";
} else {
    echo json_encode(['connection' => false, 'error' => 'Invalid server type selected']);
    exit;
}

// --- FIX FOR ODBC DRIVER 18 ---
$connectionOptions = [
    "Database" => $db_name,
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY),
    "CharacterSet" => "UTF-8",
    "LoginTimeout" => 5,
    "TrustServerCertificate" => 1, // <--- THIS IS REQUIRED FOR ODBC 18
    "Encrypt" => 0 // Optional: ensure encryption doesn't break local connections
];

try {
    $conn = sqlsrv_connect($db_config['host'], $connectionOptions);

    if ($conn) {
        $tables_to_check = ['MEMB_INFO', 'MEMB_STAT', 'Guild', 'MuCastle_DATA', 'Character'];
        $table_results = [];
        
        foreach ($tables_to_check as $table) {
            $sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
            $stmt = sqlsrv_query($conn, $sql, [$table]);
            $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : ['count' => 0];
            $table_results[$table] = ($row['count'] > 0);
        }
        
        sqlsrv_close($conn);
        echo json_encode(['connection' => true, 'tables' => $table_results]);
    } else {
        $errors = sqlsrv_errors();
        $error_msg = ($errors) ? $errors[0]['message'] : 'Unknown error';
        
        // Friendly error for login failed
        if (strpos($error_msg, 'Login failed') !== false) {
             $error_msg = "Login failed. Please check the SQL User/Password in Settings.";
        }
        
        echo json_encode(['connection' => false, 'error' => 'Connection Failed: ' . $error_msg]);
    }
} catch (Exception $e) {
    echo json_encode(['connection' => false, 'error' => 'System Exception: ' . $e->getMessage()]);
}
?>