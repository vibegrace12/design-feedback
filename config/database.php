<?php
// Database Configuration

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', 'design_feedback-system');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Create connection
$conn = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASSWORD,
    DB_NAME,
    DB_PORT
);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset to utf8mb4
$conn->set_charset('utf8mb4');

?>
