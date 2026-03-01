<?php
session_start();

if (isset($_GET['server'])) {
    $new_server = $_GET['server'];
    
    // CHANGED TO admin_server SO IT DOES NOT OVERWRITE THE PLAYER!
    if ($new_server === 'mid') {
        $_SESSION['admin_server'] = 'mid'; 
    } else if ($new_server === 'hard') {
        $_SESSION['admin_server'] = 'hard'; 
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '../dashboard.php';
header("Location: $redirect");
exit;
?>