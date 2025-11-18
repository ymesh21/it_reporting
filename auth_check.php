<?php
// auth_check.php
require_once 'config.php'; // Includes session_start()

function check_auth() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== TRUE) {
        header("Location: login.php");
        exit();
    }
}

// Function to check if the user has the required role
function has_role($required_roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    // Convert a single string role into an array for consistency
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($_SESSION['user_role'], $required_roles);
}

// In auth_check.php:
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}
