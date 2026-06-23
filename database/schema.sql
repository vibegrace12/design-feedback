-- Design Feedback System Database Schema
-- Database: `design_feedback-system`

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'designer', 'reviewer') DEFAULT 'designer',
    avatar_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
);

-- 2. Projects Table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    client_id INT NOT NULL,
    owner_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Project Permissions Table
CREATE TABLE project_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('viewer', 'editor', 'admin') DEFAULT 'viewer',
    granted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_project_user (project_id, user_id),
    INDEX idx_project_permissions_user (user_id),
    INDEX idx_project_permissions_project (project_id)
);

-- 4. Design Versions Table
CREATE TABLE design_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    version_number INT NOT NULL,
    file_url TEXT NOT NULL,
    uploaded_by INT,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_design_versions_project (project_id)
);

-- 5. Contextual Pins Table
CREATE TABLE comment_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_id INT NOT NULL,
    user_id INT NOT NULL,
    x_percentage NUMERIC(5,2) NOT NULL,
    y_percentage NUMERIC(5,2) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    severity VARCHAR(20) DEFAULT 'Minor',
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (version_id) REFERENCES design_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_comment_pins_version (version_id)
);

-- 6. Threaded Comments Table
CREATE TABLE pin_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pin_id) REFERENCES comment_pins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pin_replies_pin (pin_id)
);

-- 7. Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    actor_id INT,
    type ENUM('pin_created', 'reply_added', 'pin_resolved', 'mentioned', 'permission_granted', 'permission_revoked') DEFAULT 'pin_created',
    related_pin_id INT,
    related_reply_id INT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_pin_id) REFERENCES comment_pins(id) ON DELETE SET NULL,
    FOREIGN KEY (related_reply_id) REFERENCES pin_replies(id) ON DELETE SET NULL,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (user_id, is_read)
);

-- 8. Feedback Reports Table
CREATE TABLE feedback_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    version_id INT,
    generated_by INT NOT NULL,
    filters JSON,
    total_pins INT DEFAULT 0,
    resolved_pins INT DEFAULT 0,
    blocker_count INT DEFAULT 0,
    minor_count INT DEFAULT 0,
    idea_count INT DEFAULT 0,
    report_data LONGTEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (version_id) REFERENCES design_versions(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_feedback_reports_project (project_id)
);

-- 9. Activity Log Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    resource_type VARCHAR(50),
    resource_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_logs_user (user_id),
    INDEX idx_activity_logs_action (action)
);
