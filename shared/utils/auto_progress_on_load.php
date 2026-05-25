<?php
/**
 * Auto-Progress Semester on Page Load
 * Call this at the beginning of dashboard pages to auto-progress semesters
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/evsu_semester_progression.php';

// Auto-progress semester if needed (runs silently in background)
try {
    autoProgressSemester($conn);
} catch (Exception $e) {
    // Silently fail - don't break page load
    error_log('Auto-progression error: ' . $e->getMessage());
}

