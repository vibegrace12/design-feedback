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
    // Check if database connection exists
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }

    session_start();
    
    if ($method === 'GET' && $action === 'list') {
        // Get all projects
        $query = "SELECT id, name, client_id, owner_id, description, created_at FROM projects ORDER BY created_at DESC";
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        
        echo json_encode(['success' => true, 'projects' => $projects]);
        
    } elseif ($method === 'GET' && $action === 'get') {
        // Get single project with versions
        $project_id = intval($_GET['id'] ?? 0);
        if (!$project_id) throw new Exception('Project ID required');
        
        $query = "SELECT id, name, client_id, owner_id, description, created_at FROM projects WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('i', $project_id);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $project = $stmt->get_result()->fetch_assoc();
        if (!$project) throw new Exception('Project not found');
        
        // Get versions
        $query = "SELECT id, version_number, file_url, uploaded_by, uploaded_at FROM design_versions WHERE project_id = ? ORDER BY version_number DESC";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('i', $project_id);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $project['versions'] = $versions;
        echo json_encode(['success' => true, 'project' => $project]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new project - requires authentication
        $current_user = getCurrentUser();
        if (!$current_user) throw new Exception('Authentication required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $client_id = intval($data['client_id'] ?? 0);
        $description = $data['description'] ?? '';
        
        if (!$name || !$client_id) throw new Exception('Name and client_id required');
        
        $query = "INSERT INTO projects (name, client_id, owner_id, description) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('siis', $name, $client_id, $current_user['id'], $description);
        
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $project_id = $stmt->insert_id;
        
        // Grant admin permission to owner
        $query = "INSERT INTO project_permissions (project_id, user_id, role, granted_by) VALUES (?, ?, 'admin', ?)";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('iii', $project_id, $current_user['id'], $current_user['id']);
            $stmt->execute();
        }
        
        // Log activity
        logActivity($conn, $current_user['id'], 'project_created', 'projects', $project_id);
        
        echo json_encode(['success' => true, 'id' => $project_id]);
        
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
