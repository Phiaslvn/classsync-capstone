<?php
/**
 * Session Configuration for EVSU-OCC Scheduling System
 * This file must be included at the very beginning of the application
 * before any session_start() calls or output
 */

// Only configure if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Session timeout (2 hours)
    ini_set('session.gc_maxlifetime', 7200);
    
    // Start session
    session_start();
    
    // Regenerate session ID on first load (before headers sent)
    if (!isset($_SESSION['session_regenerated']) && !headers_sent()) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = true;
    }
}
?>
