<?php
/**
 * Database connection — see config/database.example.php for XAMPP defaults.
 * Optional: create config/database.local.php to override without editing this file.
 */
if (is_readable(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
    return;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'u747906436_2025_EVSU'; // Rename to match your imported DB (e.g. classsync)  


// Initialize connection variable to prevent undefined variable errors
$conn = null;
$db_connection_error = null;

try {
    // Set connection timeout to prevent hanging (5 seconds)
    ini_set('default_socket_timeout', 5);
    
    $conn = @new mysqli($host, $user, $pass, $db);
    
    // Check if connection was successful
    if ($conn && $conn->connect_error) {
        // Connection failed
        error_log('Database connection failed: ' . $conn->connect_error);
        $db_connection_error = $conn->connect_error;
        $conn->close();
        $conn = null;
    } elseif ($conn) {
        // Connection successful - set timeout options
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    }
} catch (Exception $e) {
    error_log('Database connection exception: ' . $e->getMessage());
    $db_connection_error = $e->getMessage();
    $conn = null;
} catch (Error $e) {
    error_log('Database connection fatal error: ' . $e->getMessage());
    $db_connection_error = $e->getMessage();
    $conn = null;
}

// Ensure $conn is available in global scope for all include methods
// This makes it work with both require_once '../../config/database.php' and require_once __DIR__ . '/../../config/database.php'
if (!isset($GLOBALS['conn'])) {
    $GLOBALS['conn'] = $conn;
}
if (!isset($GLOBALS['db_connection_error'])) {
    $GLOBALS['db_connection_error'] = $db_connection_error;
}

// You can now use $conn in your other PHP files
// Works with both: require_once '../../config/database.php' and require_once __DIR__ . '/../../config/database.php'