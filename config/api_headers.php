<?php
/**
 * API Response Headers
 * Include this in ALL API/AJAX endpoints
 * 
 * This prevents CDN and browsers from caching API responses
 */

// Prevent caching of API responses
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// JSON response
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Additional headers to prevent CDN caching
header('X-Accel-Buffering: no'); // Nginx specific
header('X-Cache-Control: no-cache'); // Custom header for CDN detection
?>

