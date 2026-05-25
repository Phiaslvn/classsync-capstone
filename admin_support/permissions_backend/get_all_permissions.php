<?php
/**
 * Admin Support – Get all permissions (for user permission modal).
 * Permission-driven: requires manage_roles; used by admin, instructor, moderator flows via same DB.
 */
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

if (!hasPermission('manage_roles')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("
        SELECT id, permission_key, permission_name, permission_display_name, module
        FROM permissions
        ORDER BY module, permission_display_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'permissions' => $permissions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
