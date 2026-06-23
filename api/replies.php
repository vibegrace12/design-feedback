<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth-helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    session_start();

    if ($method === 'GET' && $action === 'list') {
        // Get all replies for a pin
        $pin_id = intval($_GET['pin_id'] ?? 0);
        if (!$pin_id) throw new Exception('Pin ID required');
        
        $query = "SELECT pr.id, pr.pin_id, pr.user_id, u.username, u.full_name, pr.comment_text, pr.created_at
                  FROM pin_replies pr
                  JOIN users u ON pr.user_id = u.id
                  WHERE pr.pin_id = ?
                  ORDER BY pr.created_at ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('i', $pin_id);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'replies' => $replies]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new reply
        $current_user = getCurrentUser();
        if (!$current_user) throw new Exception('Authentication required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $pin_id = intval($data['pin_id'] ?? 0);
        $comment_text = $data['comment_text'] ?? '';
        
        if (!$pin_id || !$comment_text) {
            throw new Exception('pin_id and comment_text required');
        }
        
        $query = "INSERT INTO pin_replies (pin_id, user_id, comment_text)
                  VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('iis', $pin_id, $current_user['id'], $comment_text);
        
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $reply_id = $stmt->insert_id;
        
        // Create notification for pin owner
        $query = "SELECT user_id FROM comment_pins WHERE id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $pin_id);
            $stmt->execute();
            $pin = $stmt->get_result()->fetch_assoc();
            if ($pin && $pin['user_id'] !== $current_user['id']) {
                createNotification($conn, $pin['user_id'], $current_user['id'], 'reply_added', $pin_id, $reply_id, 'New reply to your feedback');
            }
        }
        
        logActivity($conn, $current_user['id'], 'reply_created', 'pin_replies', $reply_id);
        
        echo json_encode(['success' => true, 'id' => $reply_id]);
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>