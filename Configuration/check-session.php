<?php
header('Content-Type: application/json');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load Settings
$settings_file = 'settings.json';
$timeout_minutes = 10; // Default fallback

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    // Use the specific USER timeout setting
    $timeout_minutes = $settings['security']['user_session_timeout_minutes'] ?? 10;
}

$timeout_duration = $timeout_minutes * 60;
$response = ['loggedIn' => false];

if (isset($_SESSION['user_loggedin']) && $_SESSION['user_loggedin'] === true) {
    if (isset($_SESSION['user_last_activity']) && (time() - $_SESSION['user_last_activity']) > $timeout_duration) {
        // TIMEOUT: Destroy session
        session_unset();
        session_destroy();
        $response['loggedIn'] = false;
        $response['reason'] = 'timeout';
    } else {
        // ACTIVE: Update last activity time
        $_SESSION['user_last_activity'] = time();
        $response['loggedIn'] = true;
    }
}

echo json_encode($response);
?>