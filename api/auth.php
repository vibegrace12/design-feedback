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
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('ssss', $username, $email, $hashed_password, $full_name);
        
        if (!$stmt->execute()) {
            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                throw new Exception('Username or email already exists');
            }
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $user_id = $stmt->insert_id;
        logActivity($conn, $user_id, 'user_registered', 'users', $user_id);
        
        echo json_encode(['success' => true, 'id' => $user_id, 'message' => 'User registered successfully']);
        
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
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        
        $stmt->bind_param('s', $email);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
        
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password');
        }
        
        // Generate session token
        $token = bin2hex(random_bytes(32));
        $user_id = $user['id'];
        
        // Store token in session
        session_start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['token'] = $token;
        $_SESSION['role'] = $user['role'];
        
        logActivity($conn, $user_id, 'user_login', 'users', $user_id);
        
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
            logActivity($conn, $user_id, 'user_logout', 'users', $user_id);
        }
        
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        
    } elseif ($method === 'GET' && $action === 'verify') {
        session_start();
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $query = "SELECT id, username, email, full_name, role, avatar_url FROM users WHERE id = ? AND is_active = TRUE";
            $stmt = $conn->prepare($query);
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            
            $stmt->bind_param('i', $user_id);
            if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);
            
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

if (isset($conn)) {
    $conn->close();
}
?>