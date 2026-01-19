<?php
session_start();
$settings = json_decode(file_get_contents(__DIR__ . '/../Configuration/settings.json'), true);
$timeout_duration = ($settings['security']['session_timeout_minutes'] ?? 30) * 60;
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

if ($is_logged_in) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header('Location: dashboard.php?status=session_expired');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
?>