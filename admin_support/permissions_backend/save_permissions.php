<?php
/**
 * Admin Support – Save user permission overrides (user_permissions table).
 * Permission-driven: requires manage_roles. Affects admin, instructor, moderator access via shared DB.
 */
require_once __DIR__ . '/../../includes/auth/security_middleware.php';

if (!hasPermission('manage_roles')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token mismatch']);
    exit;
}

$acc_id = isset($_POST['acc_id']) ? (int) $_POST['acc_id'] : 0;
if ($acc_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Accept both formats:
// - New: permissions[id]=0|1 (from hidden+checkbox in user_management_tab)
// - Legacy: permissions[]=id (array of checked IDs only, from manage_roles.php)
$permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
    ? $_POST['permissions']
    : [];

try {
    $conn->begin_transaction();
    $del = $conn->prepare("DELETE FROM user_permissions WHERE acc_id = ?");
    $del->bind_param('i', $acc_id);
    $del->execute();
    $del->close();

    $ins = $conn->prepare("INSERT INTO user_permissions (acc_id, permission_id, allowed) VALUES (?, ?, ?)");
    $keys = array_keys($permissions);
    $isNewFormat = !empty($keys) && $keys[0] !== 0; // New format has permission IDs as keys
    if ($isNewFormat) {
        foreach ($permissions as $pid => $allowed) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $allowed = ((int) $allowed === 1) ? 1 : 0;
            $ins->bind_param('iii', $acc_id, $pid, $allowed);
            $ins->execute();
        }
    } else {
        foreach ($permissions as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $allowed = 1;
            $ins->bind_param('iii', $acc_id, $pid, $allowed);
            $ins->execute();
        }
    }
    $ins->close();
    $conn->commit();
    logAdminAction($_SESSION['acc_id'], 'update_user_permissions', "Updated permissions for acc_id $acc_id");
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    if ($back !== '' && strpos($back, 'admin_support') !== false) {
        header('Location: ' . $back . (strpos($back, '?') !== false ? '&' : '?') . 'status=perm_saved');
        exit;
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
