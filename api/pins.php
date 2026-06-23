<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'list') {
        // Get all pins for a design version
        $version_id = intval($_GET['version_id'] ?? 0);
        if (!$version_id) throw new Exception('Version ID required');
        
        $query = "SELECT cp.id, cp.version_id, cp.user_id, cp.x_percentage, cp.y_percentage, 
                         cp.category, cp.severity, cp.is_resolved, cp.created_at
                  FROM comment_pins cp
                  WHERE cp.version_id = ?
                  ORDER BY cp.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $version_id);
        $stmt->execute();
        $pins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'pins' => $pins]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new pin
        $data = json_decode(file_get_contents('php://input'), true);
        $version_id = intval($data['version_id'] ?? 0);
        $user_id = intval($data['user_id'] ?? 0);
        $x = floatval($data['x_percentage'] ?? 0);
        $y = floatval($data['y_percentage'] ?? 0);
        $category = $data['category'] ?? 'General';
        $severity = $data['severity'] ?? 'Minor';
        
        if (!$version_id || !$user_id) throw new Exception('version_id and user_id required');
        
        $query = "INSERT INTO comment_pins (version_id, user_id, x_percentage, y_percentage, category, severity)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiddss', $version_id, $user_id, $x, $y, $category, $severity);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        
    } elseif ($method === 'PUT' && $action === 'update') {
        // Update pin (resolve/unresolve)
        $data = json_decode(file_get_contents('php://input'), true);
        $pin_id = intval($data['id'] ?? 0);
        $is_resolved = intval($data['is_resolved'] ?? 0);
        
        if (!$pin_id) throw new Exception('Pin ID required');
        
        $query = "UPDATE comment_pins SET is_resolved = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $is_resolved, $pin_id);
        
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
