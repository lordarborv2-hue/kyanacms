<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_loggedin'])) { exit; }

$user = $_SESSION['user_id'];
$flag_file = "../uploads/status_{$user}.txt";

if (file_exists($flag_file)) {
    $status = file_get_contents($flag_file);
    if ($status === 'paid') {
        unlink($flag_file); // Delete the flag so it doesn't trigger again
        echo json_encode(['status' => 'success', 'message' => 'Payment received! Credits added.']);
        exit;
    }
}

echo json_encode(['status' => 'pending']);
?>