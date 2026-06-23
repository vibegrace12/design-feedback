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
        // Get user notifications
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        $unread_only = isset($_GET['unread']) ? intval($_GET['unread']) : 0;
        
        $where = 'WHERE user_id = ?';
        $params = [$current_user['id']];
        
        if ($unread_only) {
            $where .= ' AND is_read = FALSE';
        }
        
        $query = "SELECT n.*, u.username as actor_name, u.avatar_url as actor_avatar
                  FROM notifications n
                  LEFT JOIN users u ON n.actor_id = u.id
                  $where
                  ORDER BY n.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $params[] = $limit;
        $params[] = $offset;
        $types = 'iii';
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get unread count
        $query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $current_user['id']);
        $stmt->execute();
        $count_result = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $count_result['unread_count']
        ]);
        
    } elseif ($method === 'PUT' && $action === 'mark-read') {
        // Mark notification as read
        $notification_id = intval($_GET['id'] ?? 0);
        if (!$notification_id) throw new Exception('Notification ID required');
        
        $query = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $notification_id, $current_user['id']);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        echo json_encode(['success' => true]);
        
    } elseif ($method === 'PUT' && $action === 'mark-all-read') {
        // Mark all notifications as read
        $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $current_user['id']);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        echo json_encode(['success' => true]);
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>