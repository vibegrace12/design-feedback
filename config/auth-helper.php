<?php

/**
 * Get current authenticated user from session
 */
function getCurrentUser() {
    session_start();
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'designer'
        ];
    }
    return null;
}

/**
 * Get user's permission level in a project
 */
function getProjectPermission($conn, $project_id, $user_id) {
    $query = "SELECT role FROM project_permissions WHERE project_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) return null;
    
    $stmt->bind_param('ii', $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result;
}

/**
 * Check if user has specific permission in project
 */
function hasProjectPermission($conn, $project_id, $user_id, $required_role = 'viewer') {
    $perm = getProjectPermission($conn, $project_id, $user_id);
    if (!$perm) return false;
    
    $role_hierarchy = ['viewer' => 1, 'editor' => 2, 'admin' => 3];
    $user_level = $role_hierarchy[$perm['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

/**
 * Create notification
 */
function createNotification($conn, $user_id, $actor_id, $type, $related_pin_id, $related_reply_id, $message) {
    $query = "INSERT INTO notifications (user_id, actor_id, type, related_pin_id, related_reply_id, message)
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('iisii s', $user_id, $actor_id, $type, $related_pin_id, $related_reply_id, $message);
        $stmt->execute();
    }
}

/**
 * Log activity
 */
function logActivity($conn, $user_id, $action, $resource_type, $resource_id, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $details_json = $details ? json_encode($details) : json_encode([]);
    
    $query = "INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details, ip_address)
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('issi ss', $user_id, $action, $resource_type, $resource_id, $details_json, $ip);
        $stmt->execute();
    }
}

?>