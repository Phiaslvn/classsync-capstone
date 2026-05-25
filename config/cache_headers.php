<?php
/**
 * Cache Control Headers for Dynamic PHP Pages
 * Include this at the top of ALL PHP pages that generate dynamic content
 * 
 * This prevents CDN from caching dynamic PHP pages that contain user-specific data
 */

// Prevent caching of dynamic PHP pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Additional headers to prevent CDN caching
header('X-Accel-Buffering: no'); // Nginx specific
header('X-Cache-Control: no-cache'); // Custom header for CDN detection
?>

