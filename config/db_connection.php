<?php
/**
 * FinPredict Database Connection
 * 
 * This file manages the database connection for the FinPredict system.
 * It uses MySQLi procedural method for database operations.
 * 
 * XAMPP Configuration:
 * - Server: localhost
 * - Username: root (default XAMPP user)
 * - Password: (empty - default XAMPP)
 * - Database: finpredict_db
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'finpredict_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 for proper character encoding
$conn->set_charset("utf8mb4");

// Error handling function
function handleDatabaseError($error_message) {
    // Log error to file (optional)
    error_log("Database Error: " . $error_message);
    
    // Display user-friendly error message
    echo "<div class='alert alert-danger'>
            <strong>Database Error:</strong> There was an issue processing your request. 
            Please try again later.
          </div>";
    
    // For development purposes, uncomment to see detailed error
    // echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($error_message) . "</div>";
    
    return false;
}

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to validate email format
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to validate numeric input
function validate_numeric($value, $min = 0, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    if ($value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}
?>
