<?php
// 1. Force the browser NOT to cache this request
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

// 2. Unset all session variables
$_SESSION = array();

// 3. Completely destroy the browser's session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session memory on the server
session_destroy();

echo json_encode(['success' => true]);
?>