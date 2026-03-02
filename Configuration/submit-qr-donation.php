<?php
header('Content-Type: application/json');
session_start();
error_reporting(0); // Prevents random PHP warnings from breaking JSON
require_once '../config.php';

if (!isset($_SESSION['user_loggedin'])) { 
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login.']); exit; 
}

$username = $_SESSION['user_id'];
$credits = (int)($_POST['credits'] ?? 0);
$reference = trim($_POST['reference'] ?? '');

// 1. Validate Inputs
if ($credits <= 0 || empty($reference) || !isset($_FILES['proof'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
}

// 2. Setup Directory
$targetDir = "../uploads/proofs/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// 3. Process File
$fileExt = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
$fileName = $username . "_" . time() . "." . $fileExt;
$targetFilePath = $targetDir . $fileName;

if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFilePath)) {
    
    // 4. Database Connection (Ensure this matches your config)
    $settings = json_decode(file_get_contents('settings.json'), true);
    $server_key = (isset($_SESSION['user_server']) && $_SESSION['user_server'] === 'hard') ? 'hard_rate' : 'mid_rate';
    $db_config = $settings['database'][$server_key];


    $conn = sqlsrv_connect($db_config['host'], [
        "Database" => $db_config['name'], 
        "Uid" => $db_config['user'],
        "PWD" => decrypt_data($db_config['pass_encrypted'], ENCRYPTION_KEY),
        "TrustServerCertificate" => 1
    ]);

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'File uploaded, but database connection failed.']); exit;
    }

    // 5. Insert Record
    $sql = "INSERT INTO PendingDonations (AccountID, CreditsToReceive, ReferenceNumber, ProofImage, Status, DateSubmitted) VALUES (?, ?, ?, ?, 0, GETDATE())";
    $params = [$username, $credits, $reference, $fileName];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo json_encode(['success' => true]);
    } else {
        // If SQL fails, show the actual SQL error for debugging
        $errors = sqlsrv_errors();
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $errors[0]['message']]);
    }
    sqlsrv_close($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Move file failed. check PHP tmp folder.']);
}
?>