<?php
// Configuration/get-sidebar-stats.php
header('Content-Type: application/json');
session_start();
error_reporting(0);

// Check if user is logged in
if (!isset($_SESSION['user_loggedin']) || $_SESSION['user_loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']); 
    exit;
}

// Determine which server the user is currently looking at
$server_key = ($_SESSION['user_server'] === 'mid') ? 'mid_rate' : 'hard_rate';
$cacheFile = __DIR__ . '/sidebar_cache_' . $server_key . '.json';

// Return the instant cached data
if (file_exists($cacheFile)) {
    echo file_get_contents($cacheFile);
} else {
    // If the cron hasn't run yet, send empty data so the UI doesn't break
    echo json_encode([
        'success' => false, 
        'error' => 'Data is currently generating. Please check back in a few minutes.'
    ]);
}
?>