<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'list') {
        // Get all projects
        $query = "SELECT id, name, client_id, created_at FROM projects ORDER BY created_at DESC";
        $result = $conn->query($query);
        
        if (!$result) throw new Exception($conn->error);
        
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        
        echo json_encode(['success' => true, 'projects' => $projects]);
        
    } elseif ($method === 'GET' && $action === 'get') {
        // Get single project with versions
        $project_id = intval($_GET['id'] ?? 0);
        if (!$project_id) throw new Exception('Project ID required');
        
        $query = "SELECT id, name, client_id, created_at FROM projects WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        
        if (!$project) throw new Exception('Project not found');
        
        // Get versions
        $query = "SELECT id, version_number, file_url, uploaded_at FROM design_versions WHERE project_id = ? ORDER BY version_number DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $project['versions'] = $versions;
        echo json_encode(['success' => true, 'project' => $project]);
        
    } elseif ($method === 'POST' && $action === 'create') {
        // Create new project
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        $client_id = intval($data['client_id'] ?? 0);
        
        if (!$name || !$client_id) throw new Exception('Name and client_id required');
        
        $query = "INSERT INTO projects (name, client_id) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $name, $client_id);
        
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
