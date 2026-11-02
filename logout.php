<?php
/**
 * PickFix - Clean Session Logout
 */

require_once __DIR__ . '/config/database.php';
pickfix_session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Restart new session for flash notification
pickfix_session_start();
$_SESSION['flash_info'] = "You have been signed out successfully.";

header("Location: login.php");
exit;

