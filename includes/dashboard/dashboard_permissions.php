<?php
/**
 * Legacy Permission System - Redirects to Unified Security Middleware
 * This file is maintained for backward compatibility
 * All functions have been moved to security_middleware.php
 */

// Include the unified security middleware instead
require_once __DIR__ . '/../auth/security_middleware.php';
?>