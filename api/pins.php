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
        // Get all pins for a design version
        $version_id = intval($_GET['version_id'] ?? 0);
        if (!$version_id) throw new Exception('Version ID required');
        
        $query = "SELECT cp.id, cp.version_id, cp.user_id, cp.x_percentage, cp.y_percentage, 
                         cp.category, cp.severity, cp.is_resolved, cp.created_at
                  FROM comment_pins cp
                  WHERE cp.version_id = ?
                  ORDER BY cp.created_at DESC";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('i', $version_id);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $pins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'pins' => $pins]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new pin
        $current_user = getCurrentUser();
        if (!$current_user) throw new Exception('Authentication required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $version_id = intval($data['version_id'] ?? 0);
        $x = floatval($data['x_percentage'] ?? 0);
        $y = floatval($data['y_percentage'] ?? 0);
        $category = $data['category'] ?? 'General';
        $severity = $data['severity'] ?? 'Minor';
        
        if (!$version_id) throw new Exception('version_id required');
        
        $query = "INSERT INTO comment_pins (version_id, user_id, x_percentage, y_percentage, category, severity)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('iiddss', $version_id, $current_user['id'], $x, $y, $category, $severity);
        
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $pin_id = $stmt->insert_id;
        logActivity($conn, $current_user['id'], 'pin_created', 'comment_pins', $pin_id);
        
        echo json_encode(['success' => true, 'id' => $pin_id]);
        
    } elseif ($method === 'PUT' && $action === 'update') {
        // Update pin (resolve/unresolve)
        $current_user = getCurrentUser();
        if (!$current_user) throw new Exception('Authentication required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $pin_id = intval($data['id'] ?? 0);
        $is_resolved = intval($data['is_resolved'] ?? 0);
        
        if (!$pin_id) throw new Exception('Pin ID required');
        
        $query = "UPDATE comment_pins SET is_resolved = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('ii', $is_resolved, $pin_id);
        
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        logActivity($conn, $current_user['id'], 'pin_updated', 'comment_pins', $pin_id);
        
        echo json_encode(['success' => true]);
        
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