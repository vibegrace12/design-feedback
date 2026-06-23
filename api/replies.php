<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'list') {
        // Get all replies for a pin
        $pin_id = intval($_GET['pin_id'] ?? 0);
        if (!$pin_id) throw new Exception('Pin ID required');
        
        $query = "SELECT id, pin_id, user_id, comment_text, created_at
                  FROM pin_replies
                  WHERE pin_id = ?
                  ORDER BY created_at ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $pin_id);
        $stmt->execute();
        $replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'replies' => $replies]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new reply
        $data = json_decode(file_get_contents('php://input'), true);
        $pin_id = intval($data['pin_id'] ?? 0);
        $user_id = intval($data['user_id'] ?? 0);
        $comment_text = $data['comment_text'] ?? '';
        
        if (!$pin_id || !$user_id || !$comment_text) {
            throw new Exception('pin_id, user_id, and comment_text required');
        }
        
        $query = "INSERT INTO pin_replies (pin_id, user_id, comment_text)
                  VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iis', $pin_id, $user_id, $comment_text);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
