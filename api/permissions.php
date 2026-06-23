<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth-helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    session_start();
    $current_user = getCurrentUser();
    if (!$current_user) throw new Exception('Unauthorized');
    
    if ($method === 'GET' && $action === 'list') {
        // List project permissions
        $project_id = intval($_GET['project_id'] ?? 0);
        if (!$project_id) throw new Exception('Project ID required');
        
        // Check if user has admin role in project
        $perm = getProjectPermission($conn, $project_id, $current_user['id']);
        if (!$perm || $perm['role'] !== 'admin') {
            throw new Exception('Insufficient permissions');
        }
        
        $query = "SELECT pp.id, pp.project_id, pp.user_id, u.username, u.email, u.full_name, pp.role, pp.created_at
                  FROM project_permissions pp
                  JOIN users u ON pp.user_id = u.id
                  WHERE pp.project_id = ?
                  ORDER BY pp.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $permissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'permissions' => $permissions]);
        
    } elseif ($method === 'POST' && $action === 'grant') {
        // Grant permission to user
        $data = json_decode(file_get_contents('php://input'), true);
        $project_id = intval($data['project_id'] ?? 0);
        $user_id = intval($data['user_id'] ?? 0);
        $role = $data['role'] ?? 'viewer';
        
        if (!$project_id || !$user_id) throw new Exception('Project ID and user ID required');
        if (!in_array($role, ['viewer', 'editor', 'admin'])) throw new Exception('Invalid role');
        
        // Check if user has admin role in project
        $perm = getProjectPermission($conn, $project_id, $current_user['id']);
        if (!$perm || $perm['role'] !== 'admin') {
            throw new Exception('Insufficient permissions');
        }
        
        // Insert or update permission
        $query = "INSERT INTO project_permissions (project_id, user_id, role, granted_by) 
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE role = VALUES(role)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iisi', $project_id, $user_id, $role, $current_user['id']);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        // Create notification
        createNotification($conn, $user_id, $current_user['id'], 'permission_granted', null, null, 
                          "You have been granted $role access to a project");
        
        // Log activity
        logActivity($conn, $current_user['id'], 'permission_granted', 'project_permissions', $stmt->insert_id);
        
        echo json_encode(['success' => true, 'message' => 'Permission granted']);
        
    } elseif ($method === 'DELETE' && $action === 'revoke') {
        // Revoke permission
        $permission_id = intval($_GET['id'] ?? 0);
        if (!$permission_id) throw new Exception('Permission ID required');
        
        // Get permission details
        $query = "SELECT project_id, user_id FROM project_permissions WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $permission_id);
        $stmt->execute();
        $perm_data = $stmt->get_result()->fetch_assoc();
        
        if (!$perm_data) throw new Exception('Permission not found');
        
        // Check if user has admin role in project
        $perm = getProjectPermission($conn, $perm_data['project_id'], $current_user['id']);
        if (!$perm || $perm['role'] !== 'admin') {
            throw new Exception('Insufficient permissions');
        }
        
        $query = "DELETE FROM project_permissions WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $permission_id);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        // Create notification
        createNotification($conn, $perm_data['user_id'], $current_user['id'], 'permission_revoked', null, null,
                          "Your access to a project has been revoked");
        
        // Log activity
        logActivity($conn, $current_user['id'], 'permission_revoked', 'project_permissions', $permission_id);
        
        echo json_encode(['success' => true, 'message' => 'Permission revoked']);
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>