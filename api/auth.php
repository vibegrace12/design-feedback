<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'POST' && $action === 'register') {
        // Register new user
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $full_name = $data['full_name'] ?? '';
        
        if (!$username || !$email || !$password) {
            throw new Exception('Username, email, and password required');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $query = "INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'designer')";
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception($conn->error);
        
        $stmt->bind_param('ssss', $username, $email, $hashed_password, $full_name);
        
        if (!$stmt->execute()) {
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                throw new Exception('Username or email already exists');
            }
            throw new Exception($stmt->error);
        }
        
        // Log activity
        logActivity($conn, $stmt->insert_id, 'user_registered', 'user', $stmt->insert_id);
        
        echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'message' => 'User registered successfully']);
        
    } elseif ($method === 'POST' && $action === 'login') {
        // Login user
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$email || !$password) {
            throw new Exception('Email and password required');
        }
        
        // Find user
        $query = "SELECT id, username, email, password, full_name, role, avatar_url FROM users WHERE email = ? AND is_active = TRUE";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password');
        }
        
        // Generate session token
        $token = bin2hex(random_bytes(32));
        $user_id = $user['id'];
        
        // Store token in session (in production, use JWT or database sessions table)
        session_start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['token'] = $token;
        $_SESSION['role'] = $user['role'];
        
        // Log activity
        logActivity($conn, $user_id, 'user_login', 'user', $user_id);
        
        // Remove password from response
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'user' => $user,
            'token' => $token
        ]);
        
    } elseif ($method === 'POST' && $action === 'logout') {
        session_start();
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            logActivity($conn, $user_id, 'user_logout', 'user', $user_id);
        }
        
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        
    } elseif ($method === 'GET' && $action === 'verify') {
        session_start();
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $query = "SELECT id, username, email, full_name, role, avatar_url FROM users WHERE id = ? AND is_active = TRUE";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user) {
                echo json_encode(['success' => true, 'authenticated' => true, 'user' => $user]);
            } else {
                session_destroy();
                echo json_encode(['success' => false, 'authenticated' => false]);
            }
        } else {
            echo json_encode(['success' => false, 'authenticated' => false]);
        }
        
    } else {
        throw new Exception('Invalid action or method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function logActivity($conn, $user_id, $action, $resource_type, $resource_id) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $details = json_encode(['endpoint' => $_SERVER['REQUEST_URI'] ?? '']);
    
    $query = "INSERT INTO activity_logs (user_id, action, resource_type, resource_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('issiis', $user_id, $action, $resource_type, $resource_id, $details, $ip);
        $stmt->execute();
    }
}

$conn->close();
?>