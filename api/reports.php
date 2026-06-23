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
    
    if ($method === 'POST' && $action === 'generate') {
        // Generate feedback report
        $data = json_decode(file_get_contents('php://input'), true);
        $project_id = intval($data['project_id'] ?? 0);
        $version_id = isset($data['version_id']) ? intval($data['version_id']) : null;
        $filters = $data['filters'] ?? [];
        
        if (!$project_id) throw new Exception('Project ID required');
        
        // Check permission
        $perm = getProjectPermission($conn, $project_id, $current_user['id']);
        if (!$perm) throw new Exception('Access denied');
        
        // Build query for pins
        $where = 'WHERE dv.project_id = ?';
        $params = [$project_id];
        $types = 'i';
        
        if ($version_id) {
            $where .= ' AND dv.id = ?';
            $params[] = $version_id;
            $types .= 'i';
        }
        
        if (!empty($filters['severity'])) {
            $where .= ' AND cp.severity = ?';
            $params[] = $filters['severity'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $where .= ' AND cp.category = ?';
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['resolved'])) {
            $resolved = intval($filters['resolved']);
            $where .= ' AND cp.is_resolved = ?';
            $params[] = $resolved;
            $types .= 'i';
        }
        
        // Get statistics
        $query = "SELECT 
                    COUNT(*) as total_pins,
                    SUM(CASE WHEN cp.is_resolved = TRUE THEN 1 ELSE 0 END) as resolved_pins,
                    SUM(CASE WHEN cp.severity = 'Blocker' THEN 1 ELSE 0 END) as blocker_count,
                    SUM(CASE WHEN cp.severity = 'Minor' THEN 1 ELSE 0 END) as minor_count,
                    SUM(CASE WHEN cp.severity = 'Idea' THEN 1 ELSE 0 END) as idea_count
                  FROM comment_pins cp
                  JOIN design_versions dv ON cp.version_id = dv.id
                  $where";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Get detailed pin data
        $query = "SELECT cp.id, cp.category, cp.severity, cp.is_resolved, 
                         u.username, u.full_name, cp.created_at,
                         COUNT(pr.id) as reply_count
                  FROM comment_pins cp
                  JOIN design_versions dv ON cp.version_id = dv.id
                  JOIN users u ON cp.user_id = u.id
                  LEFT JOIN pin_replies pr ON cp.id = pr.pin_id
                  $where
                  GROUP BY cp.id
                  ORDER BY cp.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $pin_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $report_data = array_merge($stats, ['pins' => $pin_details]);
        $report_json = json_encode($report_data);
        
        // Store report
        $filters_json = json_encode($filters);
        $query = "INSERT INTO feedback_reports 
                  (project_id, version_id, generated_by, filters, total_pins, resolved_pins, 
                   blocker_count, minor_count, idea_count, report_data)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iiiisiiiis', $project_id, $version_id, $current_user['id'], $filters_json,
                         $stats['total_pins'], $stats['resolved_pins'], $stats['blocker_count'],
                         $stats['minor_count'], $stats['idea_count'], $report_json);
        
        if (!$stmt->execute()) throw new Exception($stmt->error);
        
        $report_id = $stmt->insert_id;
        
        // Log activity
        logActivity($conn, $current_user['id'], 'report_generated', 'feedback_reports', $report_id);
        
        echo json_encode([
            'success' => true,
            'report_id' => $report_id,
            'data' => $report_data
        ]);
        
    } elseif ($method === 'GET' && $action === 'list') {
        // List project reports
        $project_id = intval($_GET['project_id'] ?? 0);
        if (!$project_id) throw new Exception('Project ID required');
        
        // Check permission
        $perm = getProjectPermission($conn, $project_id, $current_user['id']);
        if (!$perm) throw new Exception('Access denied');
        
        $query = "SELECT fr.id, fr.generated_by, u.username, u.full_name, 
                         fr.total_pins, fr.resolved_pins, fr.blocker_count, 
                         fr.minor_count, fr.idea_count, fr.generated_at
                  FROM feedback_reports fr
                  JOIN users u ON fr.generated_by = u.id
                  WHERE fr.project_id = ?
                  ORDER BY fr.generated_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'reports' => $reports]);
        
    } elseif ($method === 'GET' && $action === 'get') {
        // Get specific report
        $report_id = intval($_GET['id'] ?? 0);
        if (!$report_id) throw new Exception('Report ID required');
        
        $query = "SELECT fr.* FROM feedback_reports fr WHERE fr.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        
        if (!$report) throw new Exception('Report not found');
        
        // Check permission
        $perm = getProjectPermission($conn, $report['project_id'], $current_user['id']);
        if (!$perm) throw new Exception('Access denied');
        
        $report['data'] = json_decode($report['report_data'], true);
        
        echo json_encode(['success' => true, 'report' => $report]);
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>