-- Design Feedback System Database Schema
-- Database: `design_feedback-system`

-- 1. Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'designer', 'reviewer') DEFAULT 'designer',
    avatar_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Projects Table
CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    client_id INT NOT NULL,
    owner_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Project Permissions Table
CREATE TABLE project_permissions (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('viewer', 'editor', 'admin') DEFAULT 'viewer',
    granted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_project_user (project_id, user_id)
);

-- 4. Design Versions Table
CREATE TABLE design_versions (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    version_number INT NOT NULL,
    file_url TEXT NOT NULL,
    uploaded_by INT,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Contextual Pins Table
CREATE TABLE comment_pins (
    id SERIAL PRIMARY KEY,
    version_id INT REFERENCES design_versions(id) ON DELETE CASCADE,
    user_id INT NOT NULL,
    x_percentage NUMERIC(5,2) NOT NULL,
    y_percentage NUMERIC(5,2) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    severity VARCHAR(20) DEFAULT 'Minor',
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Threaded Comments Table
CREATE TABLE pin_replies (
    id SERIAL PRIMARY KEY,
    pin_id INT REFERENCES comment_pins(id) ON DELETE CASCADE,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Notifications Table
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    actor_id INT,
    type ENUM('pin_created', 'reply_added', 'pin_resolved', 'mentioned') DEFAULT 'pin_created',
    related_pin_id INT,
    related_reply_id INT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_pin_id) REFERENCES comment_pins(id) ON DELETE SET NULL,
    FOREIGN KEY (related_reply_id) REFERENCES pin_replies(id) ON DELETE SET NULL
);

-- 8. Feedback Reports Table
CREATE TABLE feedback_reports (
    id SERIAL PRIMARY KEY,
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
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 9. Activity Log Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    resource_type VARCHAR(50),
    resource_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes for performance
CREATE INDEX idx_design_versions_project ON design_versions(project_id);
CREATE INDEX idx_comment_pins_version ON comment_pins(version_id) WHERE is_resolved = FALSE;
CREATE INDEX idx_pin_replies_pin ON pin_replies(pin_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(user_id, is_read);
CREATE INDEX idx_project_permissions_user ON project_permissions(user_id);
CREATE INDEX idx_project_permissions_project ON project_permissions(project_id);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_users_email ON users(email);
