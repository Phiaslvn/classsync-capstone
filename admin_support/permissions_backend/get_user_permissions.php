<?php
/**
 * Admin Support – Get user's effective permission IDs (role defaults + user overrides).
 * Returns permission IDs the user actually has: from role_permissions, plus user_permissions
 * where allowed=1, minus user_permissions where allowed=0.
 */
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

if (!hasPermission('manage_roles')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$acc_id = isset($_GET['acc_id']) ? (int) $_GET['acc_id'] : 0;
if ($acc_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // 1. Get role-based permissions
    $stmt = $conn->prepare("
        SELECT rp.permission_id
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        WHERE ur.acc_id = ?
    ");
    $stmt->bind_param('i', $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[(int) $row['permission_id']] = true;
    }
    $stmt->close();

    // 2. Apply user overrides (allowed=1 adds, allowed=0 removes)
    $stmt = $conn->prepare("
        SELECT permission_id, allowed
        FROM user_permissions
        WHERE acc_id = ?
    ");
    $stmt->bind_param('i', $acc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pid = (int) $row['permission_id'];
        $allowed = (int) $row['allowed'];
        if ($allowed === 1) {
            $ids[$pid] = true;
        } else {
            unset($ids[$pid]);
        }
    }
    $stmt->close();

    echo json_encode(array_values(array_keys($ids)));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}
