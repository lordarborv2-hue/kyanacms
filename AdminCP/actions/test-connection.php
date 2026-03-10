<?php
error_reporting(0); // Prevents PHP errors from breaking JSON output
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['loggedin'])) { 
    echo json_encode(['connection' => false, 'error' => 'Access Denied']); 
    exit; 
}

require_once '../../config.php';
$settings = json_decode(file_get_contents('../../Configuration/settings.json'), true);

// Get the correct server configuration
$server = $_POST['server'] ?? 'mid';
$db_config = ($server === 'hard') ? $settings['database']['hard_rate'] : $settings['database']['mid_rate'];

$connectionOptions = [
    "Database" => $db_config['name'],
    "Uid" => $db_config['user'],
    "PWD" => decrypt_pass($db_config['pass_encrypted'], ENCRYPTION_KEY), // Use your existing decrypt function
    "TrustServerCertificate" => 1,
    "Encrypt" => 0
];

$conn = sqlsrv_connect($db_config['host'], $connectionOptions);

if ($conn) {
    $tables = ['MEMB_INFO', 'MEMB_STAT', 'Character', 'Warehouse', 'WebCredits', 'WebshopItems'];
    $results = [];
    foreach ($tables as $t) {
        $check = sqlsrv_query($conn, "SELECT 1 FROM sys.tables WHERE name = ?", [$t]);
        $results[$t] = ($check && sqlsrv_has_rows($check));
    }
    echo json_encode(['connection' => true, 'tables' => $results]);
} else {
    $errors = sqlsrv_errors();
    echo json_encode(['connection' => false, 'error' => $errors[0]['message']]);
}