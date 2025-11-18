<?php
/**
 * Logout Script for IT Reporting System
 * Clears the session and redirects the user to the login page.
 */

// 1. Ensure the session is started
// This is necessary to access and destroy session variables.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all session variables
// $_SESSION = array() is a common alternative to clear all data.
$_SESSION = array();

// 3. Destroy the session
// This removes the session ID from the server and deletes the session file.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // Delete the session cookie by setting its expiration time in the past
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// 4. Redirect to the login page
// Use a 302 Found status code for the redirection.
header("Location: login.php");
exit;
?>